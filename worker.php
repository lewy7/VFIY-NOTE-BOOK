<?php
$title_limit = 200;
$text_limit = 3800;
$file_number_limit = 4096;
$data_dir = './vfxxdata/';
error_reporting(0);
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0777);
    touch($data_dir . "index.html");
}
function auth($auth)
{
    global $data_dir;
    if (strlen($auth) != 8) {
        //die('{"response":"failure","message":"auth error length"}');
        die('{"response":"failure","message":"auth error"}');
    }

    $log = fopen($data_dir.'access.log', 'a+');
    fprintf($log, "%s,%s,%s,%s\n", $_SERVER['REMOTE_ADDR'], $_GET['action'], $_GET['auth'], date("y-m-d H:i:s", time()));
    fclose($log);

    $ip = $_SERVER['REMOTE_ADDR'];

    $ipArr = array();
    $i = 0;
    $now = time();
    $mark = 0;
    $fp = fopen($data_dir . 'ip', 'rb');
    if ($fp) {
        while (!feof($fp)) {
            //127.0.0.1\t 5 \t 1585223223 \n
            $s = fgets($fp, 256);
            if (strlen($s) < 20) continue;

            list($ipa, $number, $date) = explode("\t", $s);
            //if($ip == $ipa) echo $ipa.'<br/>';
            //if($number > 10) echo $number.'<br/>';
            //if($date+3600 > $now) echo $date.'<br/>'.$now.'<br/>';

            if ($ip == $ipa && $number > 10 && $date + 3600 > $now) {
                $ipArr[$i] = $ipa . "\t" . $number . "\t" . $now;
                $mark = 1;
            } else {
                $ipArr[$i] = rtrim($s);
            }
            $i++;
        }
        fclose($fp);
    }
    //print_r($ipArr);
    if ($mark == 1) {
        $fp = fopen($data_dir . 'ip', 'wb');
        foreach ($ipArr as $s) {
            fprintf($fp, "%s\n", $s);
        }
        fclose($fp);
        //die('{"response":"failure","message":"auth error times"}');
        die('{"response":"failure","message":"auth error"}');
    }


    $fp = fopen($data_dir . 'auth', 'r');
    if ($fp == false) {
        $fp = fopen($data_dir . 'auth', 'w');
        fprintf($fp, "%s", $auth);
        fclose($fp);
    } else {
        $buffer = fgets($fp, 10);
        fclose($fp);
        if (0 != strncmp($buffer, $auth, 8)) {
            $mark = 0;
            $fp = fopen($data_dir . 'ip', 'wb');

            foreach ($ipArr as $s) {
                list($ipa, $number, $date) = explode("\t", $s);
                if ($ip == $ipa) {
                    if ($date + 3600 < $now)
                        $number = 1;
                    else
                        $number++;
                    $s = $ipa . "\t" . $number . "\t" . $now;
                    $mark = 1;
                }
                fprintf($fp, "%s\n", $s);
            }
            if ($mark == 0) {
                fprintf($fp, "%s\t1\t%s", $ip, $now);
            }
            fclose($fp);

            //die('{"response":"failure","message":"auth error auth"}');
            die('{"response":"failure","message":"auth error"}');


        }
    }
    return true;
}

auth($_GET['auth']);


if ($_GET['action'] == 'add') {

    if (strlen($_GET['title']) > $title_limit) {
        die('{"response":"failure","message":"title too long"}');
    }
    if (strlen($_GET['text']) > $text_limit) {
        die('{"response":"failure","message":"content too long"}');
    }

    $i = 0;
    if ($dir = opendir($data_dir)) {
        while (false !== ($file = readdir($dir))) {
            if ($file != "." && $file != "..") {
                $i++;
            }
        }
        closedir($dir);
    }
    if ($i > $file_number_limit) {
        die('{"response":"failure","message":"to much data items"}');
    }

    $pattern = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

    while (1) {
        $id = '';
        for ($i = 0; $i < 6; $i++) {
            $id .= $pattern{rand(0, 61)};
        }
        $time = time();
        $file = $data_dir . 'UD_' . $time . '_' . $id;
        //echo $file;
        $fp = fopen($file, 'r');
        if ($fp == false)
            break;
        fclose($fp);
    }

    $fp = fopen($file, 'w');
    if ($fp == false) {
        die('{"response":"failure","message":"open data file false"}');
    }
    fprintf($fp, "%s\n%s", $_GET['title'], $_GET['text']);
    fclose($fp);

    die('{"response":"success","id":"' . $id . '","title":"' . $_GET['title'] . '","text":"' . $_GET['text'] . '","date":"' . date("y-m-d H:i", $time) . '"}');
}

if ($_GET['action'] == 'delete') {
    $id = htmlspecialchars(addslashes($_GET['id']));
    if (strlen($id) != 6) {
        die('{"response":"failure","message":"id error"}');
    }
    if ($dir = opendir($data_dir)) {
        while (false !== ($file = readdir($dir))) {
            if ($file != "." && $file != ".." && 0 == strncmp($file, 'UD_', 3)) {
                list($head, $date, $fid) = explode('_', $file);
                //echo $fid."<br/>";
                if ($fid == $id)
                    break;
            }
        }
        closedir($dir);
    }
    if ($fid == $id) {
        if (!unlink($data_dir . $file)) {
            die('{"response":"failure","message":"delete error"}');
        }
        die('{"response":"success","id":"' . $id . '"}');
    } else {

        die('{"response":"failure","message":"no such id"}');
    }

}

if ($_GET['action'] == 'query') {
    //echo '{"number":"2","items":[{"id":"2JKJ1cc","title":"1111","text":"xvcxvsdvs","date":"156154545"},{"id":"JKJ2","title":"2222","text":"xvcxvsdvs2222","date":"12151515"}]}';

    $i = 0;
    $arr = array();
    if ($dir = opendir($data_dir)) {
        while (false !== ($file = readdir($dir))) {
            if ($file != "." && $file != ".." && 0 == strncmp($file, 'UD_', 3)) {
                $arr[$i++] = $file;
            }
        }
        closedir($dir);
    }
    $total = $i;
    $start = $_GET['start'];
    $range = $_GET['range'];

    //echo $total.'  '.$start.'  '.$range.'<br/>';
    if ($start > $total || $total == 0) {
        die('{"response":"success","number":"0"}');
    } elseif ($start + $range > $total) {
        $number = $total - $start;
    } else {
        $number = $range;
    }
    $json = '{"response":"success","number":"' . $number . '","items":[';
    rsort($arr);
    for ($i = $start; $i < $start + $number; $i++) {
        list($head, $date, $id) = explode('_', $arr[$i]);
        $buffer = file_get_contents($data_dir . $arr[$i]);
        list($title, $text) = explode("\n", $buffer);
        $json .= '{"id":"' . $id . '","title":"' . $title . '","text":"' . $text . '","date":"' . date("y-m-d H:i", $date) . '"},';
    }
    $json = rtrim($json, ',');
    $json .= ']}';
    die($json);
}


?>
