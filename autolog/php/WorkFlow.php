<?php
	define('DOCROOT', dirname(__FILE__));
	include_once DOCROOT . "/PDOHandler.php";

	class WorkFlow {
		private $allowedOp = 'Ativado';
		private $allowedStation = '61';
		
		private $serial = null;
		private $opid = null;
		private $line = null;
		
		private $kSgqdUsuarios = null;
		private $handleUsuario = null;
		
		private $kSgqdTestes = null;
		private $kSgqdRotasPop = null;
		private $kSgqdProximaEtapa = null;
		private $testSelectStmt = null;
		private $handleProduto = null;
		private $actualHandleEtapa = null;
		private $nextHandleEtapa = null;
		
		function __construct() {
			$this->serial = (isset($_POST['serial'])) ? $_POST['serial'] : null;
			$this->opid = (isset($_POST['opid'])) ? $_POST['opid'] : null;
			$this->line = (isset($_POST['line'])) ? $_POST['line'] : null;
		}
		
		function start() {
			$pdo = PDOHandler::getPDOInstance();
			
			if($this->checkOpid($pdo)) {
				$this->getSerialSteps($pdo);
				$this->checkSerialSteps();
			} else {
				
			}
		}
		
		function checkOpid($pdo) {
			$stmt = $pdo->prepare('SELECT STATUS FROM K_SGQD_USUARIOS WHERE HANDLE = :handle');
			$stmt->bindParam(':handle', $this->opid);
			
			$flag = $stmt->execute();
			
			if($flag) {
				$this->kSgqdUsuarios = $stmt->fetchALL(PDO::FETCH_ASSOC);
				
				if(isset($this->kSgqdUsuarios[0])) {
					if($this->kSgqdUsuarios[0]['STATUS'] == $this->allowedOp) {
						return true;
					}
				}
			}
			
			return false;
		}
		
		function getSerialSteps($pdo) {
			$stmt = $pdo->prepare('SELECT * FROM K_SGQD_PROXIMA_ETAPA WHERE NUMEROSERIE = :serial');
			$stmt->bindParam(':serial', $this->serial);
			
			$flag = $stmt->execute();
			
			if($flag) {
				$this->kSgqdProximaEtapa = $stmt->fetchALL(PDO::FETCH_ASSOC);

				if(isset($this->kSgqdProximaEtapa[0])) {
					$this->actualHandleEtapa = $this->kSgqdProximaEtapa[0]['HANDLEETAPAATUAL'];
					$this->nextHandleEtapa = $this->kSgqdProximaEtapa[0]['HANDLEPROXIMAETAPA'];
				}
			}
		}
		
		function checkSerialSteps() {
			if($this->actualHandleEtapa == $this->allowedStation) {
				
			} else {
				
			}
		}
	}

	$workFlow = new WorkFlow();

	if(!empty($_POST)) {
		$workFlow->start();
	}
?>