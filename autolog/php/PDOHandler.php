<?php
	class PDOHandler {
		private static $pdo;
		
		private function __construct() { }
		
		public static function getPDOInstance() {
			if(!isset(self::$pdo)) {
				$con_oracle_IP      = "10.0.0.214";
				$con_oracle_SID     = "DLTEST";
				$con_oracle_PORTA   = "1521";
				$con_oracle_SENHA   = "masterkey";
				$con_oracle_USER    = "sysman_corp";
				$charset 						= "utf-8";
				
				$tns = "(DESCRIPTION =
													(ADDRESS_LIST =
														(ADDRESS =
															(PROTOCOL = TCP)
															(HOST = $con_oracle_IP)
															(PORT = $con_oracle_PORTA)
														)
													)
													(CONNECT_DATA = 
														(SID = $con_oracle_SID)
													)
												)";

				$opt = [
					PDO::ATTR_ERRMODE 						=> PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE 	=> PDO::FETCH_ASSOC,
					PDO::ATTR_EMULATE_PREPARES 		=> false,
				];
				
				try {
					self::$pdo = new PDO("oci:dbname=$tns", $con_oracle_USER, $con_oracle_SENHA, $opt);
				} catch(PDOException $e) {
					echo $e->getMessage();
				}
			}
			
			return self::$pdo;
		}
	}
?>