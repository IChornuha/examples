<?php
// define('TEEN_ELEMENT_PICES_TO_REMOVE', 'Заказ №');


if (is_string($argv['1']) && ($argv!='')&&is_string($argv['2'])){
	
	if ($argv['1']!="--help") {
		$cl_path 	= $argv['1'];
		$prefix 	= $argv['2'];
		if ($cl_path[strlen($cl_path)-1]!="/"){
				$cl_path.="/";
		}
	}else{
		echo getHelp();
	}
}else{
	// $cl_path = "../test/";
	exit("Справка: --help\n");
}
if (file_exists($cl_path)&&is_dir($cl_path)){
	if (!empty($prefix)){
		$liqpayH 	= fopen("./csv/".$prefix."liqpay.csv", 'a');
		$personalH	= fopen("./csv/".$prefix."personal.csv", 'a');
		$paymentH	= fopen("./csv/".$prefix."payment.csv", 'a');


		$fileResultList = getFileList($cl_path);
		$progres=1;
		foreach ($fileResultList as $key => $filePath) {

				$r = new Reader($filePath);
				echo "$filePath\n";
				echo "Обработка файла\t\t$progres из ".count($fileResultList)."\n";
				$aResult = $r->readForLiqpay();
				array_shift($aResult); //delete the '<?php' string
				// print_r($aResult);
				if (!empty($aResult)){
				
						$infOnPaymentArray = getInfOnPaymentArray($aResult);
							writeExportFile($paymentH, $infOnPaymentArray);
						$liqpayDataArray   = getLiqpayDataArray($aResult);
							writeExportFile($liqpayH, $liqpayDataArray);
						$personalInfArray  = getPersonalDataArray($aResult);
							writeExportFile($personalH, $personalInfArray);
				
						// print_r($infOnPaymentArray);
						// print_r($liqpayDataArray);
						// print_r($personalInfArray);
				
						$progres++;
				}
			}
		echo "Обработано файлов:\t      ".$progres."\n";
	}else{
		echo "Укажите префикс для имени файла.\n";
	}

}else{
	echo "Ошибка указания пути: путь отсутствует или указывает не на папку.\n";
}
function getHelp()
{
	return "
	Скрипт для формирования *.csv файлов для импорта в БД\n
	Параметры:\n
	\tpath:\tпуть к папке, с которой начать обход,\n
	\tfile prefix:\tпрефикс для файлов.\n
	Файлы сохраняются в  каталоге скрипта.\n

	";
}




function writeExportFile($csvHandler, $arrayToWrite){
	if (is_resource($csvHandler)){
		fputcsv($csvHandler, $arrayToWrite, ',');
	}

}
function getLiqpayDataArray($mixedArray){
	$transformedDate = transformDate($mixedArray['14']);

	$resultList = array(
						0=>$mixedArray['3'],
						1=>$mixedArray['7'],
						2=>substr($mixedArray['10'], 14),
						3=>$mixedArray['9'],
						4=>$mixedArray['10'],
						5=>$mixedArray['11'],
						6=>$mixedArray['12'],
						7=>$mixedArray['13'],
						8=>$mixedArray['2'],
						9=>$mixedArray['1'],
						10=>$mixedArray['0'],
						11=>$mixedArray['4'],
						12=>$transformedDate
				);
	return $resultList;
}

function getInfOnPaymentArray($mixedArray){
	$period = explode(" ", $mixedArray['31']);
	$transformedDate = transformDate($mixedArray[14]);
	$resultList = array(
					0=>substr($mixedArray['10'], 14),
					1=>$mixedArray['3'],
					2=>getSectorCode(substr($mixedArray['10'], 14)),
					3=>$mixedArray['24'],
					4=>$period['4']." ".$period['5']." ".$period['6'],
					5=>$period['8']." ".$period['9']." ".$period['10'],
					6=>$transformedDate
				);
	return $resultList;
}
function getPersonalDataArray($mixedArray){
	$FIO = explode(" ", $mixedArray['18']);
 	$resultList = array(
						0=>substr($mixedArray['10'], 14),
						1=>$mixedArray['20'],
						2=>$FIO['0'],
						3=>$FIO['1'],
						4=>$FIO['2'],
						5=>$mixedArray['19'],
						6=>$mixedArray['2'],
						7=>$mixedArray['3']
				);
	return $resultList;
 } 
function getPaymentPeriod($input){
	$period = array();
	$PosX = strpos($input, "від ")+7;
	$PosY = strpos($input, "до ")+5;
	$period['from'] = substr($input, $PosX, $PosY-$PosX-4);
	$period['to']   = substr($input, $PosY, 24);
	return $period;
}
function getSectorCode($orderId){
	while (strlen($orderId)< 11) {
		$orderId = "0".$orderId;
	}
	$sectorCode = substr($orderId, 0, 3);
	$sectorCode .="_";
	$sectorCode .= substr($orderId, 3, 2);
	return $sectorCode;
}
function transformDate($temp){
	$temp1 = $temp;

	$temp1['0'] =$temp['6']; 
	$temp1['1'] =$temp['7'];
	$temp1['2'] =$temp['8'];
	$temp1['3'] =$temp['9'];
	$temp1['4'] ="-";
	$temp1['5'] =$temp['3'];
	$temp1['6'] =$temp['4'];
	$temp1['7'] ="-";
	$temp1['8'] =$temp['0'];
	$temp1['9'] =$temp['1'];
	return $temp1;
}

class Reader 
{
	private $filename;
	function __construct($filename)
	{	
		$this->filename = $filename;
	}
	public function filename ()
	{
		return $this->filename;
	}
	private function __openfile ()
	{
		$handler = fopen($this->filename, 'r+');
		return (is_resource($handler))?$handler:false;
	}
	private function __readfile($hFile)
	{
		$line = fgets($hFile,4096);
		return ((!empty($line))&&($line))?$line:false;
	}
	private function __parseLine($haystack, $needleX='="', $needleY='";')
	{
		$position['X'] = (strpos($haystack, $needleX))+2;
		$position['Y'] = strpos($haystack, $needleY);
		$parsed = substr($haystack, $position['X'], ($position['Y'])-$position['X']);
		return $parsed;
	}

	public function readForLiqpay()
	{
		$handler = $this->__openfile($this->filename);
		if ($handler) {
			$iterator=0;
			$tempArray = array();
			while (/*$i<= 16*/!feof($handler)) {
				$line = $this->__readfile($handler);
				$parsedLine = $this->__parseLine($line);
				array_push($tempArray, $parsedLine);
				$iterator++;
			}
			return $tempArray;
		}
	}
	public function filterEmptyIndex(array $arrayForFilter){
		foreach ($arrayForFilter as $key => $value) {
			if (empty($value)){
				unset($arrayForFilter[$key]);
			}
		}
		return $arrayForFilter;
	}
}

function getFileList($path){
	$List = array();
	$iterator = new DirectoryIterator($path);
	while($iterator->valid()) {
	    $file = $iterator->current();
	    if (!$file->isDot()){
		    if ($file!="_notes")
		    	if (!$file->isDir()){
		    		  array_push($List,$file->getPath()."/".$file->getFilename());
		    	}
		    		$pices = getFileList($file->getPath()."/".$file);
						foreach ($pices as $value) {
							array_push($List,$value);
						}
		    		
		    	
	    }
	    $iterator->next();
	}	
	return $List;
}

?>
