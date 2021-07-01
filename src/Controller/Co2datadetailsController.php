<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Datasource\ConnectionManager;

/**
 * Co2datadetails Controller
 *
 * @property \App\Model\Table\Co2datadetailsTable $Co2datadetails
 * @method \App\Model\Entity\Co2datadetail[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class Co2datadetailsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Table data   
        $connection = ConnectionManager::get('default');

        $devices = $connection->execute("SELECT c.co2_device_id AS device, c.temperature, c.humidity, c.co2, c.noise, r.room_no AS room FROM Co2datadetails c JOIN Room_Info r ON c.co2_device_id = r.device_id, (SELECT cc.id, cc.co2_device_id, MAX(cc.time_measured) AS maxDate FROM Co2datadetails cc GROUP BY cc.co2_device_id) my WHERE c.co2_device_id=my.co2_device_id AND c.time_measured=my.maxDate AND c.time_measured >= CURDATE();")->fetchAll('assoc');

        $this->set(compact('devices'));

        // co2datadetail table query
        $currentDateTime = date('Y-m-d H:m:s');

        $query = $this->Co2datadetails->find()
            ->select(['co2_device_id', 'temperature', 'humidity', 'co2', 'noise', 'time_measured', 'room' => 'r.room_no'])
            ->join(['r' => ['table' => 'Room_Info', 'type' => 'INNER', 'conditions' => 'r.device_id = Co2datadetails.co2_device_id']])
            // ->where(['Co2datadetails.time_measured >=' => $currentDateTime])
            ->order(['co2_device_id' => 'ASC', 'time_measured' => 'DESC'])
            ->limit(86400)
            ->toArray();

        // declare for each graph data array
        $num_devices = $temp = $hum = $co2 = $noise = [];
        $current_dev = $next_dev = '';

        // data split with censor data loop
        foreach ($query as $row) {
            // time measured standard schema
            $dateArr = (array) $row["time_measured"];
            $dateStr = implode("", $dateArr);
            $date = explode(".", $dateStr);

            // array push for each graph
            array_push($temp, array($row["co2_device_id"], $date[0], $row["temperature"]));
            array_push($hum, array($row["co2_device_id"], $date[0], $row["humidity"]));
            array_push($co2, array($row["co2_device_id"], $date[0], $row["co2"]));
            array_push($noise, array($row["co2_device_id"], $date[0], $row["noise"]));

            // number of device
            $current_dev = $row["co2_device_id"];
            if ($current_dev != $next_dev)
                array_push($num_devices, array($row["co2_device_id"]));
            $next_dev = $row["co2_device_id"];
        }

        $tempalldata = $humalldata = $co2alldata = $noisealldata = [];
        $dname = "dvTest";
        $i = 1;

        // data split with device loop
        for ($i; $i <= count($num_devices); $i++) {

            ${"temp$i"} = ${"hum$i"} = ${"co2$i"} = ${"noise$i"} = [];

            foreach ($temp as $tempdata) {
                if ($tempdata[0] == $dname . $i)
                    array_push(${"temp$i"}, $tempdata);
            }
            array_push($tempalldata, ${"temp$i"});

            foreach ($hum as $humdata) {
                if ($humdata[0] == $dname . $i)
                    array_push(${"hum$i"}, $humdata);
            }
            array_push($humalldata, ${"hum$i"});

            foreach ($co2 as $co2data) {
                if ($co2data[0] == $dname . $i)
                    array_push(${"co2$i"}, $co2data);
            }
            array_push($co2alldata, ${"co2$i"});

            foreach ($noise as $noisedata) {
                if ($noisedata[0] == $dname . $i)
                    array_push(${"noise$i"}, $noisedata);
            }
            array_push($noisealldata, ${"noise$i"});
        }

        // sent array data to template
        $this->set(compact('tempalldata', 'humalldata', 'co2alldata', 'noisealldata', 'num_devices'));

        // date time max min query
        $datequery = $this->Co2datadetails->find()
            ->select(['startdate'  => 'min(time_measured)', 'enddate'  => 'max(time_measured)'])
            ->toArray();

        // date time format conversion
        $convstartdate = $datequery[0]->startdate;
        $startsec = strtotime($convstartdate);
        $start = date("Y-m-d", $startsec);
        $start2 = date("H:i", $startsec);
        $startdate = $start . 'T' . $start2;

        $convenddate = $datequery[0]->enddate;
        $endsec = strtotime($convenddate);
        $end = date("Y-m-d", $endsec);
        $end2 = date("H:i", $endsec);
        $enddate = $end . 'T' . $end2;

        // sent array data to template
        $this->set(compact('startdate', 'enddate'));

    }

    public function csv()
    {
        // get value from query url
        $starttime = $this->request->getQuery('start-time');
        $endtime = $this->request->getQuery('end-time');
        $dev_name = $this->request->getQuery('select-device');

        // csv file download name
        $this->response = $this->response->withDownload('co2datadetails.csv');

        // csv file query
        $csv_arr = $this->Co2datadetails->find()
            ->select(['id', 'co2_device_id', 'temperature', 'humidity', 'co2', 'noise', 'time_measured', 'room' => 'r.room_no'])
            ->join(['r' => ['table' => 'Room_Info', 'type' => 'INNER', 'conditions' => 'r.device_id = Co2datadetails.co2_device_id']])
            ->where(['Co2datadetails.co2_device_id LIKE' => $dev_name, 'Co2datadetails.time_measured >=' => $starttime, 'Co2datadetails.time_measured <=' => $endtime])
            ->order(['co2_device_id' => 'ASC', 'time_measured' => 'DESC']);
        $_serialize = 'csv_arr';
        $_header = ['ID', '装置名', '温度', '湿度', 'CO2', 'ノイズ', '測定時間', '部屋'];
        $_extract = ['id', 'co2_device_id', 'temperature', 'humidity', 'co2', 'noise', 'time_measured', 'room'];

        $this->viewBuilder()->setClassName('CsvView.Csv');

        // downloading file
        $this->set(compact('csv_arr', '_serialize', '_header', '_extract'));
    }

    public function view()
    {
        // graph data query
        $query = $this->Co2datadetails->find()
            ->select(['co2_device_id', 'temperature', 'humidity', 'co2', 'noise', 'time_measured', 'room' => 'r.room_no'])
            ->join(['r' => ['table' => 'Room_Info', 'type' => 'INNER', 'conditions' => 'r.device_id = Co2datadetails.co2_device_id']])
            // ->where(['Co2datadetails.time_measured >=' => $currentDateTime])
            ->order(['time_measured' => 'ASC', 'co2_device_id' => 'ASC',])
            ->limit(30000)
            ->toArray();

        // declare for each graph data array
        $num_devices = $temp = $hum = $co2 = $noise = [];
        $dv1 = $dv2 = $dv3 = [];
        $current_dev = $next_dev = '';

        // data split with censor data loop
        foreach ($query as $row) {
            // time measured standard schema
            $dateArr = (array) $row["time_measured"];
            $dateStr = implode("", $dateArr);
            $date = explode(".", $dateStr);

            // array push for each graph
            array_push($temp, array($date[0], $row["co2_device_id"], $row["temperature"]));
            array_push($hum, array($date[0], $row["co2_device_id"], $row["humidity"]));
            array_push($co2, array($date[0], $row["co2_device_id"], $row["co2"]));
            array_push($noise, array($date[0], $row["co2_device_id"], $row["noise"]));

            if ($row["co2_device_id"] == 'dvTest1')
                array_push($dv1, array($date[0], $row["temperature"], $row["humidity"], $row["co2"], $row["noise"], $row["co2_device_id"]));
            if ($row["co2_device_id"] == 'dvTest2')
                array_push($dv2, array($date[0], $row["temperature"], $row["humidity"], $row["co2"], $row["noise"], $row["co2_device_id"]));
            if ($row["co2_device_id"] == 'dvTest3')
                array_push($dv3, array($date[0], $row["temperature"], $row["humidity"], $row["co2"], $row["noise"], $row["co2_device_id"]));

            // number of device
            $current_dev = $row["co2_device_id"];
            if ($current_dev != $next_dev)
                array_push($num_devices, array($row["co2_device_id"]));
            $next_dev = $row["co2_device_id"];
        }

        // sent array data to template
        $this->set(compact('temp', 'hum', 'co2', 'noise', 'dv1', 'dv2', 'dv3'));
    }
}