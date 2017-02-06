<?php
	define('DOCROOT', dirname(__FILE__));
	include_once DOCROOT . "/PDOHandler.php";

	class WorkFlow {
		// Flow control
		private $allowedOp = 'Ativado';
		private $allowedStation = '61';

		// Informed by user
		private $scanNumeroSerie = null;
		private $scanHandleUsuario = null;
		private $scanHandleLinha = null;

		// User table
		private $kSgqdUsuarios = null;
		private $handleUsuario = null;

		// Product table
		private $kSgqdTestes = null;
		private $kSgqdHistorico = null;
		private $kSgqdRotasPop = null;
		private $kSgqdProximaEtapa = null;
		private $kSgqdEtapas = null;

		// Product info
		private $dataCadastro = null;
		private $hora = null;

		function __construct() {
			$this->scanNumeroSerie = (isset($_POST['serial'])) ? $_POST['serial'] : null;
			$this->scanHandleUsuario = (isset($_POST['opid'])) ? $_POST['opid'] : null;
			$this->scanHandleLinha = (isset($_POST['line'])) ? $_POST['line'] : null;

			$timeZone = 'America/Sao_Paulo';
			$timeStamp = time();
			$dateTime = new DateTime("now", new DateTimeZone($timeZone));
			$dateTime->setTimestamp($timeStamp);

			$this->dataCadastro = $dateTime->format('d-m-Y');
			$this->hora = $dateTime->format('d-m-Y H:i:s');
		}

		// Flow start
		function start() {
			try {
				$pdo = PDOHandler::getPDOInstance();

				$status = 'fail';
				$message = 'Problemas durante a requisição';
				$details = 'Verifique se as informações escaneadas estão corretas e tente novamente!';

				if($this->checkOpid($pdo)) {
					if($this->getSerialSteps($pdo)) {
						if($this->getStepName($pdo)) {
							$handleEtapaAtual = $this->kSgqdProximaEtapa['HANDLEETAPAATUAL'];

							if($handleEtapaAtual == $this->allowedStation) {
								if($this->insertTestTable($pdo)) {
									if($this->insertHistoricTable($pdo)) {
										if($this->updateNextStepTable($pdo)) {
											if($this->getSerialSteps($pdo) && $this->getStepName($pdo)) {
												$status = 'success';
												$message = 'Produto logado no sistema!';
												$details = 'Próximo posto: ' . $this->kSgqdEtapas['NOME'];

												$this->jsonResponse($status, $message, $details);
											} else {
												return $this->jsonResponse($status, $message, $details);
											}
										} else {
											return $this->jsonResponse($status, $message, $details);
										}
									} else {
										return $this->jsonResponse($status, $message, $details);
									}
								} else {
									return $this->jsonResponse($status, $message, $details);
								}
							} else {
								$message = 'Número de série inválido ou não está na rota correta';
								$details = 'Próximo posto: ' . $this->kSgqdEtapas['NOME'];

								return $this->jsonResponse($status, $message, $details);
							}
						} else {
							return $this->jsonResponse($status, $message, $details);
						}
					} else {
						return $this->jsonResponse($status, $message, $details);
					}
				} else {
					$details = 'O código do operador é inválido!';

					return $this->jsonResponse($status, $message, $details);
				}
			} catch(PDOException $e) {
				return $this->jsonResponse($status, $message, $details);
			}
		}

		// Return response to web page
		function jsonResponse($status, $message, $details) {
			$jsonData = array(
				'status' => $status,
				'message' => $message,
				'details' => $details
			);

			echo json_encode($jsonData);
		}

		// Insert or update the given values in the table
		function insertOrUpdate($pdo, $query, $queryData) {
			$stmt = $this->query($pdo, $query, $queryData);

			if(isset($stmt)) {
				return $stmt->rowCount();
			}

			return 0;
		}

		// Select the first row returned by a query
		function selectRow($pdo, $query, $queryData) {
			$rows = $this->select($pdo, $query, $queryData);

			if(sizeof($rows) > 0) {
				$row = $rows[0];

				if(isset($row)) {
					return $row;
				}
			}

			return null;
		}

		// Select all rows returned by a query
		function select($pdo, $query, $queryData) {
			$stmt = $this->query($pdo, $query, $queryData);

			if(isset($stmt)) {
				$table = $stmt->fetchALL(PDO::FETCH_ASSOC);
				return $table;
			}

			return null;
		}

		// Generic method to make one of the basic (CRUD) queries
		function query($pdo, $query, $queryData) {
			if(isset($queryParams) && isset($queryData) || !(isset($queryParams) && isset($queryData))) {
				try {
					$stmt = $pdo->prepare($query);

					foreach ($queryData as $key => &$value) {
						$stmt->bindParam($key, $value, PDO::PARAM_STR);
					}

					if($stmt->execute()) {
						return $stmt;
					}
				} catch(PDOException $e) {
					/*$status = 'fail';
					$message = 'Problemas durante a requisição';
					$details = 'Verifique se as informações escaneadas estão corretas e tente novamente';

					$this->jsonResponse($status, $message, $details);*/
				}

				return null;
			}
		}

		// Check if OPID is activated
		function checkOpid($pdo) {
			$query = 'SELECT STATUS FROM K_SGQD_USUARIOS WHERE HANDLE = :handleusuario';

			$queryData = json_decode('{
				":handleusuario":"' . $this->scanHandleUsuario . '"
			}');

			$this->kSgqdUsuarios = $this->selectRow($pdo, $query, $queryData);

			if(isset($this->kSgqdUsuarios)) {
				return $this->kSgqdUsuarios['STATUS'] == $this->allowedOp;
			}

			return false;
		}

		// Get actual and next product steps
		function getSerialSteps($pdo) {
			$query = 'SELECT HANDLEETAPAATUAL, HANDLEPROXIMAETAPA FROM K_SGQD_PROXIMA_ETAPA WHERE NUMEROSERIE = :numeroserie';

			$queryData = json_decode('{
				":numeroserie":"' . $this->scanNumeroSerie . '"
			}');

			$this->kSgqdProximaEtapa = $this->selectRow($pdo, $query, $queryData);

			return isset($this->kSgqdProximaEtapa);
		}

		// Get the lastest step where the product were located
		function getStepName($pdo) {
			$query = 'SELECT NOME FROM K_SGQD_ETAPAS WHERE HANDLE = :handleetapa';

			$handleProximaEtapa = $this->kSgqdProximaEtapa['HANDLEPROXIMAETAPA'];

			$jsonData = '{
				":handleetapa":"' . $handleProximaEtapa . '"
			}';

			$queryData = json_decode($jsonData);

			$this->kSgqdEtapas = $this->selectRow($pdo, $query, $queryData);

			return isset($this->kSgqdEtapas);
		}

		// Get K_SGQD_TESTES table
		function getTestTable($pdo) {
			$query = 'SELECT HANDLEIDENTIFICACAO, HANDLEOP, NUMEROVOLTASRO, NUMEROVOLTASAST, HANDLEPRODUTO, DATACRIACAOPECA, DEFEITOMESMADATACRIACAO FROM K_SGQD_TESTES WHERE HANDLEETAPA = :handleetapa AND NUMEROSERIE = :numeroserie';

			$handleEtapa = $this->kSgqdProximaEtapa['HANDLEETAPAATUAL'];
			$numeroSerie = $this->scanNumeroSerie;

			$queryData = json_decode('{
				":handleetapa":"' . $handleEtapa . '",
				":numeroserie":"' . $numeroSerie . '"
			}');

			return $this->selectRow($pdo, $query, $queryData);
		}

		// Insert product info on K_SGQD_TESTES table
		function insertTestTable($pdo) {
			$this->kSgqdTestes = $this->getTestTable($pdo);

			$handleIdentificacao = $this->kSgqdTestes['HANDLEIDENTIFICACAO'];
			$handleEtapa = $this->kSgqdProximaEtapa['HANDLEPROXIMAETAPA'];
			$handleLinha = $this->scanHandleLinha;
			$handleOp = $this->kSgqdTestes['HANDLEOP'];
			$handleDefeito = '106';
			$tipoDefeito = 'Nao possui defeito';
			$dataCadastro = $this->dataCadastro;
			$hora = $this->hora;
			$situacao = 'Conforme';
			$obs = 'Etapa: ' . $this->kSgqdEtapas['NOME'];
			$numeroVoltasRo = $this->kSgqdTestes['NUMEROVOLTASRO'];
			$numeroVoltasAst = $this->kSgqdTestes['NUMEROVOLTASAST'];
			$status = 'Aprovado na etapa: ' . $this->kSgqdEtapas['NOME'];
			$handleProduto = $this->kSgqdTestes['HANDLEPRODUTO'];
			$handleUsuario = $this->scanHandleUsuario;
			$dataCriacaoPeca = $this->kSgqdTestes['DATACRIACAOPECA'];
			$defeitoMesmaDataCriacao = $this->kSgqdTestes['DEFEITOMESMADATACRIACAO'];
			$etapaProducao = 'TESTES_PRODUCAO_FINALIZADO_SUCESSO';
			$numeroSerie = $this->scanNumeroSerie;

			$query = 'INSERT INTO K_SGQD_TESTES (HANDLEIDENTIFICACAO, HANDLEETAPA, HANDLELINHA, HANDLEOP, HANDLEDEFEITO, TIPODEFEITO, DATACADASTRO, HORA, SITUACAO, OBS, NUMEROVOLTASRO, NUMEROVOLTASAST, STATUS, HANDLEPRODUTO, HANDLEUSUARIO, DATACRIACAOPECA, DEFEITOMESMADATACRIACAO, ETAPAPRODUCAO, NUMEROSERIE
			) VALUES (:handleIdentificacao, :handleEtapa, :handleLinha, :handleOp, :handleDefeito, :tipoDefeito, to_date(:dataCadastro, \'DD-MM-YYYY\'), to_date(:hora, \'DD-MM-YYYY HH24:MI:SS\'), :situacao, :obs, :numeroVoltasRo, :numeroVoltasAst, :status, :handleProduto, :handleUsuario, :dataCriacaoPeca, :defeitoMesmaDataCriacao, :etapaProducao, :numeroSerie
			)';

			$queryData = json_decode('{
				":handleIdentificacao":"' . $handleIdentificacao . '",
				":handleEtapa":"' . $handleEtapa . '",
				":handleLinha":"' . $handleLinha . '",
				":handleOp":"' . $handleOp . '",
				":handleDefeito":"' . $handleDefeito . '",
				":tipoDefeito":"' . $tipoDefeito . '",
				":dataCadastro":"' . $dataCadastro . '",
				":hora":"' . $hora . '",
				":situacao":"' . $situacao . '",
				":obs":"' . $obs . '",
				":numeroVoltasRo":"' . $numeroVoltasRo . '",
				":numeroVoltasAst":"' . $numeroVoltasAst . '",
				":status":"' . $status . '",
				":handleProduto":"' . $handleProduto . '",
				":handleUsuario":"' . $handleUsuario . '",
				":dataCriacaoPeca":"' . $dataCriacaoPeca . '",
				":defeitoMesmaDataCriacao":"' . $defeitoMesmaDataCriacao . '",
				":etapaProducao":"' . $etapaProducao . '",
				":numeroSerie":"' . $numeroSerie . '"
			}');

			$rowCount = $this->insertOrUpdate($pdo, $query, $queryData);

			return $rowCount > 0;
		}

		// Get K_SGQD_HISTORICO table
		function getHistoricTable($pdo) {
			$query = 'SELECT HANDLEIDENTIFICACAO, HANDLEOP, HANDLEPRODUTO, NUMEROOP, DATACRIACAOPECA FROM K_SGQD_HISTORICO WHERE HANDLEETAPA = :handleetapa AND NUMEROSERIE = :numeroserie';

			$handleEtapa = $this->kSgqdProximaEtapa['HANDLEETAPAATUAL'];
			$numeroSerie = $this->scanNumeroSerie;

			$queryData = json_decode('{
				":handleetapa":"' . $handleEtapa . '",
				":numeroserie":"' . $numeroSerie . '"
			}');

			return $this->selectRow($pdo, $query, $queryData);
		}

		// Insert product info on K_SGQD_HISTORICO table
		function insertHistoricTable($pdo) {
			$this->kSgqdHistorico = $this->getHistoricTable($pdo);

			$handleIdentificacao = $this->kSgqdHistorico['HANDLEIDENTIFICACAO'];
			$handleOp = $this->kSgqdHistorico['HANDLEOP'];
			$handleLinha = $this->scanHandleLinha;
			$handleProduto = $this->kSgqdHistorico['HANDLEPRODUTO'];
			$handleDefeito = '106';
			$handleSintoma = '106';
			$handleCausa = '106';
			$handleSolucao = '61';
			$handleEtapa = $this->kSgqdProximaEtapa['HANDLEPROXIMAETAPA'];
			$data = $this->dataCadastro;
			$hora = $this->hora;
			$horaEntrada = $this->hora;
			$horaSaida = $this->hora;
			$foiConsertada = 'Sem defeito';
			$foiRo = 'Nao';
			$foiAst = 'Nao';
			$nomeEtapa = $this->kSgqdEtapas['NOME'];
			$origem = 'Sem origem';
			$numeroOp = $this->kSgqdHistorico['NUMEROOP'];
			$situacao = 'Conforme';
			$status = 'Aprovado na etapa: ' . $this->kSgqdEtapas['NOME'];
			$handleUsuario = $this->scanHandleUsuario;
			$dataCriacaoPeca = $this->kSgqdHistorico['DATACRIACAOPECA'];
			$etapaProducao = 'TESTES_PRODUCAO_FINALIZADO_SUCESSO';
			$numeroSerie = $this->scanNumeroSerie;

			$query = 'INSERT INTO K_SGQD_HISTORICO (HANDLEIDENTIFICACAO, HANDLEOP, HANDLELINHA, HANDLEPRODUTO, HANDLEDEFEITO, HANDLESINTOMA, HANDLECAUSA, HANDLESOLUCAO, HANDLEETAPA, DATA, HORA, HORAENTRADA, HORASAIDA, FOICONSERTADA, FOIRO, FOIAST, NOMEETAPA, ORIGEM, NUMEROOP, SITUACAO, STATUS, HANDLEUSUARIO, DATACRIACAOPECA, ETAPAPRODUCAO, NUMEROSERIE
			) VALUES (:handleIdentificacao, :handleop, :handlelinha, :handleproduto, :handledefeito, :handlesintoma, :handlecausa, :handlesolucao, :handleetapa, to_date(:data, \'DD-MM-YYYY HH24:MI:SS\'), to_date(:hora, \'DD-MM-YYYY HH24:MI:SS\'), to_date(:horaentrada, \'DD-MM-YYYY HH24:MI:SS\'), to_date(:horasaida, \'DD-MM-YYYY HH24:MI:SS\'), :foiconsertada, :foiro, :foiast, :nomeetapa, :origem, :numeroop, :situacao, :status, :handleusuario, :datacriacaopeca, :etapaproducao, :numeroserie
			)';

			$queryData = json_decode('{
				":handleIdentificacao":"' . $handleIdentificacao . '",
				":handleop":"' . $handleOp . '",
				":handlelinha":"' . $handleLinha . '",
				":handleproduto":"' . $handleProduto . '",
				":handledefeito":"' . $handleDefeito . '",
				":handlesintoma":"' . $handleSintoma . '",
				":handlecausa":"' . $handleCausa . '",
				":handlesolucao":"' . $handleSolucao . '",
				":handleetapa":"' . $handleEtapa . '",
				":data":"' . $data . '",
				":hora":"' . $hora . '",
				":horaentrada":"' . $horaEntrada . '",
				":horasaida":"' . $horaSaida . '",
				":foiconsertada":"' . $foiConsertada . '",
				":foiro":"' . $foiRo . '",
				":foiast":"' . $foiAst . '",
				":nomeetapa":"' . $nomeEtapa . '",
				":origem":"' . $origem . '",
				":numeroop":"' . $numeroOp . '",
				":situacao":"' . $situacao . '",
				":status":"' . $status . '",
				":handleusuario":"' . $handleUsuario . '",
				":datacriacaopeca":"' . $dataCriacaoPeca . '",
				":etapaproducao":"' . $etapaProducao . '",
				":numeroserie":"' . $numeroSerie . '"
			}');

			$rowCount = $this->insertOrUpdate($pdo, $query, $queryData);

			return $rowCount > 0;
		}

		// Get order of actual test station
		function getOrdem($pdo) {
			$query = 'SELECT ORDEM FROM K_SGQD_ROTAS_POP WHERE HANDLEPRODUTO = :handleproduto AND HANDLEETAPA = :handleetapa';

			$handleProduto = $this->kSgqdTestes['HANDLEPRODUTO'];
			$handleEtapa = $this->kSgqdProximaEtapa['HANDLEPROXIMAETAPA'];

			$queryData = json_decode('{
				":handleproduto":"' . $handleProduto . '",
				":handleetapa":"' . $handleEtapa . '"
			}');

			return $this->selectRow($pdo, $query, $queryData)['ORDEM'] + 1;
		}

		// Get K_SGQD_ROTAS_POP table
		function getPopRoutesTable($pdo, $ordem) {
			$query = 'SELECT HANDLEETAPA FROM K_SGQD_ROTAS_POP WHERE HANDLEPRODUTO = :handleproduto AND ORDEM = :ordem';

			$handleProduto = $this->kSgqdTestes['HANDLEPRODUTO'];

			$queryData = json_decode('{
				":handleproduto":"' . $handleProduto . '",
				":ordem":"' . $ordem . '"
			}');

			return $this->selectRow($pdo, $query, $queryData);
		}

		// Update actual and next station of K_SGQD_PROXIMA_ETAPA
		function updateNextStepTable($pdo) {
			$ordem = $this->getOrdem($pdo);

			if(isset($ordem)) {
				$this->kSgqdRotasPop = $this->getPopRoutesTable($pdo, $ordem);

				if(isset($this->kSgqdRotasPop)) {
					$query = 'UPDATE K_SGQD_PROXIMA_ETAPA SET HANDLEETAPAATUAL = :handleEtapaAtual, HANDLEPROXIMAETAPA = :handleProximaEtapa WHERE NUMEROSERIE = :numeroserie';

					$handleEtapaAtual = $this->kSgqdProximaEtapa['HANDLEPROXIMAETAPA'];
					$handleProximaEtapa = $this->kSgqdRotasPop['HANDLEETAPA'];
					$numeroSerie = $this->scanNumeroSerie;

					$queryData = json_decode('{
						":handleEtapaAtual":"' . $handleEtapaAtual . '",
						":handleProximaEtapa":"' . $handleProximaEtapa . '",
						":numeroserie":"' . $numeroSerie . '"
					}');

					$rowCount = $this->insertOrUpdate($pdo, $query, $queryData);

					return $rowCount > 0;
				}
			}

			return false;
		}
	}

	// Start WorkFlow
	$workFlow = new WorkFlow();

	if(!empty($_POST)) {
		$workFlow->start();
	}
?>
