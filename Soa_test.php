<?php if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class Soa_Test extends CI_Controller {

	public function __construct() {
		parent::__construct();
	}

	public function index() {
		echo 'soa test' . PHP_EOL;
		$this->load->library('soa/soa_client');
		$cloud = $this->soa_client->getInstance();
		// $cloud->putEnv('app', 'test');
		// $cloud->putEnv('appKey', 'test1234');
		$cloud->addServers(array('127.0.0.1:8888'));
		var_dump($cloud);
		$s = microtime(true);
		$ok = $err = 0;
		for ($i = 0; $i < 1; $i++) {
			$s2 = microtime(true);

			$ret1 = $cloud->task("App\\Test1::test1", ["hello{$i}_1"], function ($retObj) {
				echo "task1 finish\n";
			});
			$ret2 = $cloud->task("App\\Test1::hello");
			$ret3 = $cloud->task("App\\Test1::test1", ["hello{$i}_3"]);
			$ret4 = $cloud->task("App\\Test1::test1", ["hello{$i}_4"]);
			$ret5 = $cloud->task("App\\Test1::test1", ["hello{$i}_5"]);
			$ret6 = $cloud->task("App\\Test1::test1", ["hello{$i}_6"]);
			//$ret7 = $cloud->task("APP\\Test1::test1", ["hello{$i}_7"]);
			$ret7 = $cloud->task("App\\Test::test1");
			//$ret8 = $cloud->task("APP\\Test1::test1", ["hello{$i}_8"]);
			$ret8 = $cloud->task("BL\\Test1::db_test");
			echo "send " . (microtime(true) - $s2) * 1000, "\n";

			$n = $cloud->wait(0.5); //500ms超时
			//表示全部OK了
			if ($n === 8) {
				var_dump($ret1->data, $ret2->data, $ret3->data, $ret4->data, $ret5->data, $ret6->data, $ret7->data, $ret8->data);
				echo "finish\n";
				$ok++;
			} else {
				echo "#{$i} \t";
				echo $ret1->code . '|' . $ret2->code . '|' . $ret3->code . '|' . $ret4->code . '|' . $ret5->code . '|' . $ret6->code . '|' . $ret7->code . '|' . $ret8->code . '|' . "\n";
				$err++;
				exit;
			}
			unset($ret1, $ret2, $ret3, $ret4, $ret5, $ret6, $ret7, $ret8);
		}
		echo "failed=$err.\n";
		echo "success=$ok.\n";
		echo "use " . (microtime(true) - $s) * 1000, "ms\n";
		unset($cloud, $ret1, $ret2);

		exit();
	}
}

/* End of file log4php.php */
/* Location: ./application/controllers/test/log4php.php */
