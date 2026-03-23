<?php

	include('newconexao.php'); //Conecta com o banco de dados
	include_once(__DIR__ . '/../../config/periodo.php'); //configura o período atual

	$isSecretaria = defined('PLANTAO_FORCADO_SECRETARIA') && PLANTAO_FORCADO_SECRETARIA === true; //Permite a secretaria bloquear, acabar com o plantão (n sei)


	//Funções que resolve a questão do professor Felipe Aciol
	function professorOcultoParaAluno($nomeProfessor) {
		$nome = strtoupper(trim((string)$nomeProfessor));
		$nome = preg_replace('/\s+/', ' ', $nome);
		return (strpos($nome, 'FELIPE') !== false) &&
			(strpos($nome, 'ACIOL') !== false || strpos($nome, 'ACCIOL') !== false);
	}

	function professorPermitidoParaAlunoFelipe($nomeProfessor) {
		$nome = strtoupper(trim((string)$nomeProfessor));
		$nome = strtr($nome, array(
			'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A',
			'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
			'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
			'Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
			'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
			'Ç'=>'C'
		));
		$nome = preg_replace('/[^A-Z0-9 ]+/', ' ', $nome);
		$nome = preg_replace('/\s+/', ' ', $nome);
		return $nome === 'PAULA MOURA F DE L PEREIRA';
	}

	// Garante que a coluna de período exista e etiqueta inscrições antigas com o período atual
	
	function ensurePeriodoColumn($conexao, $periodoAtual) {
		$hasColumn = false;
		$check = $conexao->query("SHOW COLUMNS FROM alunosplantao LIKE 'periodo'");
		if ($check !== false && $check->rowCount() > 0) {
			$hasColumn = true;
		}

		if (!$hasColumn) {
			// Tenta migrar schema legado; se não tiver permissão, segue sem período.
			try {
				$conexao->exec("ALTER TABLE alunosplantao ADD COLUMN periodo VARCHAR(10) DEFAULT NULL");
			} catch (Exception $e) {
			}
			$recheck = $conexao->query("SHOW COLUMNS FROM alunosplantao LIKE 'periodo'");
			$hasColumn = ($recheck !== false && $recheck->rowCount() > 0);
		}

		// Marca inscrições sem período como pertencentes ao período vigente (compatibilidade)
		if ($hasColumn) {
			$stmt = $conexao->prepare("UPDATE alunosplantao SET periodo = :p WHERE periodo IS NULL");
			if ($stmt !== false) {
				$stmt->execute([':p' => $periodoAtual]);
			}
		}

		// Compatibilidade com o novo formato de horário (AAAA-MM-DD|dia - HH:MM às HH:MM)
		$colHorario = $conexao->query("SHOW COLUMNS FROM alunosplantao LIKE 'horario'");
		if ($colHorario !== false) {
			$rowHorario = $colHorario->fetch(PDO::FETCH_ASSOC);
			if ($rowHorario && isset($rowHorario['Type'])) {
				$typeHorario = strtolower(trim((string)$rowHorario['Type']));
				$precisaAjusteHorario = false;
				if (preg_match('/^varchar\((\d+)\)$/', $typeHorario, $m)) {
					$precisaAjusteHorario = intval($m[1]) < 100;
				} elseif (preg_match('/^char\((\d+)\)$/', $typeHorario, $m)) {
					$precisaAjusteHorario = intval($m[1]) < 100;
				} elseif (strpos($typeHorario, 'enum(') === 0) {
					$precisaAjusteHorario = true;
				}
				if ($precisaAjusteHorario) {
					$conexao->exec("ALTER TABLE alunosplantao MODIFY COLUMN horario VARCHAR(120) NOT NULL");
				}
			}
		}

		// Remove índices únicos legados que bloqueiam múltiplos horários para a mesma matrícula
		$idxRows = $conexao->query("SHOW INDEX FROM alunosplantao");
		if ($idxRows !== false) {
			$uniqueIndexes = array();
			while ($idx = $idxRows->fetch(PDO::FETCH_ASSOC)) {
				if (!isset($idx['Non_unique']) || intval($idx['Non_unique']) !== 0) {
					continue;
				}
				$keyName = isset($idx['Key_name']) ? $idx['Key_name'] : '';
				if ($keyName === '' || strtoupper($keyName) === 'PRIMARY') {
					continue;
				}
				$seq = isset($idx['Seq_in_index']) ? intval($idx['Seq_in_index']) : 0;
				if (!isset($uniqueIndexes[$keyName])) {
					$uniqueIndexes[$keyName] = array();
				}
				$uniqueIndexes[$keyName][$seq] = strtolower(trim((string)$idx['Column_name']));
			}

			foreach ($uniqueIndexes as $idxName => $colsBySeq) {
				ksort($colsBySeq);
				$cols = array_values($colsBySeq);
				$temMatricula = in_array('matricula', $cols, true);
				$temHorario = in_array('horario', $cols, true);

				if ($temMatricula && !$temHorario) {
					$idxSafe = str_replace('`', '``', $idxName);
					$conexao->exec("ALTER TABLE alunosplantao DROP INDEX `".$idxSafe."`");
				}
			}
		}

		return $hasColumn;
	}

	$periodoAtual = isset($PERIODO_PLANTAO_ATUAL) ? $PERIODO_PLANTAO_ATUAL : '2026.1';
	$temPeriodo = ensurePeriodoColumn($conexao, $periodoAtual);

	function debug_to_console($data) {
		$output = $data;
		if (is_array($output))
			$output = implode(',', $output);
	
		echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
	}

	$arrayNomes = array();

	// Garante que a matrícula exista antes de consultar o banco (aceita também via GET em ambiente de teste)
	if (!isset($matricula) || !is_numeric($matricula)) {
		$matriculaInput = $_POST['matricula'] ?? $_GET['matricula'] ?? null;
		if ($matriculaInput !== null && is_numeric($matriculaInput)) {
			$matricula = $matriculaInput;
		} else {
			echo "<h4 class=\"text-center\">Matrícula não informada. Acesse pelo portal do aluno.</h4>";
			return;
		}
	}

	$sqlEma = "SELECT * FROM alunos WHERE matricula = $matricula";
	$queryEma = $conexao->query($sqlEma);
	if ($queryEma === false) {
		echo "<h4 class=\"text-center\">Não foi possível carregar seus dados. Tente novamente.</h4>";
		return;
	}
	$resultEma = $queryEma->fetchAll(PDO::FETCH_ASSOC);
	
	$disciplina = $resultEma[0]['disciplina'];
	$meuProfessor = $resultEma[0]['professor'];

	// Secretaria: usa apenas o card superior (o resumo principal já está em mode_inscricaoPlantaoSecretaria)
	
	$sqlProfessor = "SELECT * FROM horariosplantao WHERE 1";
	$queryProfessor = $conexao->query($sqlProfessor);
	$result = $queryProfessor->fetchAll( PDO::FETCH_ASSOC );
	$cont = 0;
	
	$sqlInscricao = "SELECT * FROM alunosplantao WHERE matricula = :mat";
	if ($temPeriodo) {
		$sqlInscricao .= " AND periodo = :per";
	}
	$stmtInscricao = $conexao->prepare($sqlInscricao);
	$paramsInscricao = [':mat' => $matricula];
	if ($temPeriodo) {
		$paramsInscricao[':per'] = $periodoAtual;
	}
	$stmtInscricao->execute($paramsInscricao);
	$resultInscricao = $stmtInscricao->fetchAll( PDO::FETCH_ASSOC );
	$rowsInscricao = count($resultInscricao);
	$inscricaoHorarios = array();
	$professorFixo = null;
	if ($rowsInscricao > 0) {
		foreach ($resultInscricao as $inscricaoAtual) {
			$inscricaoHorarios[] = trim($inscricaoAtual['horario']);
		}
		$professorFixo = $resultInscricao[0]['professor'];
		if (!$isSecretaria && professorOcultoParaAluno($professorFixo)) {
			$professorFixo = null;
		}
	}
	if ($isSecretaria) {
		// Secretaria pode trocar professor e sobrepor inscrições
		$professorFixo = null;
	}
	$isStatusRequest = (isset($_GET['slotstatus']) && $_GET['slotstatus'] == '1');

	// Consulta chave de inscrição para permitir edição quando aberta
	$statusChave = 0;
	$sqlChave = "SELECT status FROM switchboard WHERE nome = 'inscricaoPlantoes(Alunos)' LIMIT 1";
	$queryChave = $conexao->query($sqlChave);
	if ($queryChave !== false) {
		$resChave = $queryChave->fetch(PDO::FETCH_ASSOC);
		if ($resChave && isset($resChave['status'])) {
			$statusChave = intval($resChave['status']);
		}
	}

	// Se já tiver inscrição e chave fechada, bloqueia (exceto secretaria)
	if (!$isSecretaria && $rowsInscricao > 0 && $statusChave != 1) {
		echo "<h4 class=\"text-center\">Você já concluiu sua inscrição de plantões.</h4>";
		echo "<p class=\"text-center\">Entrar em contato com a secretaria caso precise de suporte.</p>";

		echo "<div class=\"list-group\">";
		foreach ($resultInscricao as $inscricao) {
			$prof = $inscricao['professor'];
			$hor  = $inscricao['horario'];
			echo "<div class=\"list-group-item\"><strong>$prof</strong><br>$hor</div>";
		}
		echo "</div>";
		return;
	}

	// Mapeia professores compatíveis
	if(count($result) <= 0)
	{
		echo 'deu bug';
		echo '<a href="http://bit.ly/IqT6zt" class="btn btn-primary btn-lg btn-block">Back</a>';	
		return;
	}
	else
	{
				for($i=0;$i<count($result);$i++)
				{
					// Na secretaria não há restrições: lista todos os professores com plantão cadastrado
					if ($isSecretaria) {
						$accept = true;
					} elseif (professorOcultoParaAluno($meuProfessor)) {
						// Alunos do Felipe podem visualizar somente a Paula.
						$accept = professorPermitidoParaAlunoFelipe($result[$i]['nome']);
					} else {
						$accept = verificaDisponibilidade($result[$i],$disciplina,$meuProfessor);
					}

				if ($accept == true)
				{
					$nome = $result[$i]['nome'];
					if (!$isSecretaria && professorOcultoParaAluno($nome)) {
						continue;
					}
					$cont++;
					if(!in_array($nome,$arrayNomes))
						array_push($arrayNomes,$nome);
				}
			}
		}

		if($cont == 0)
		{
			echo "<div class=\"alert alert-warning text-center\">Nenhum professor liberado para sua disciplina/configuração atual. Contate a secretaria para liberação.</div>";
			return;
		}

		if (!$isStatusRequest && !$isSecretaria) {
			// Wrapper para afastar bloco da margem esquerda
			echo '<div class="plantao-wrapper" style="padding-left:20px;padding-right:20px;">';
				echo '<h2 style="text-align:center; color: black;">Cadastro em Plantões</h2><br>';
				// Passo a passo de inscrição (exibido antes da escolha do professor)
				echo '
				<div class="alert alert-info" style="align-items:center;text-align:left;">
					<h3 style = "text-align:center;font-weight: bold; margin-bottom: 30px">Orientação para inscrição de alunos(as) nos plantões</h3>
					<h4>Você escolherá o(a) professor(a), as datas e horários dos plantões que deseja realizar durante o semestre.</h4>
					<strong>1)</strong> Selecione <strong>o(a) professor(a)</strong> com quem deseja fazer seus plantões. Só é possível escolher um(a) professor(a) para plantão por todo o semestre;<br>
					<strong>2)</strong> Selecione, <strong>obrigatoriamente, 5 dias e horários</strong> em que deseja realizar seus plantões. Ao Escolher uma data você verá o número de vagas ainda disponíveis naquele dia;<br>
					<strong>3)</strong> Após definir seus dias e horários de plantão, clique em <strong>“Confirmar Escolha de Plantões”</strong>.<br><br> 
					<h4 style = "color: brown; text-align: center;"><strong>Atenção</strong></h4>
							<ul style = "font-size:14px;color: brown; text-align: center;">Caso precise, você poderá, a qualquer momento durante o semestre, alterar dias e horários conforme sua necessidade e a disponibilidade de vagas.</strong></ul>
							<ul style = "font-size:14px;color: brown;text-align: center;">Apenas não é possível mudar o(a) professor(a) escolhido(a).</ul>
				</div>';

				if ($rowsInscricao > 0 && $statusChave == 1) {
					echo "<div class=\"alert alert-warning\">Você já possui cadastro em plantões. Uma nova escolha substituirá a atual.</div>";
					//echo "<script>alert('Você já fez cadastro em plantões. Você poderá editar enquanto o cadastro em plantões estiver habilitado, mas uma nova escolha substituirá a atual.');</script>";
				}
		}

	// Professor selecionado (padrão: primeiro)
			if ($professorFixo !== null) {
				$profSelecionado = $professorFixo;
			} else {
				$profSelecionado = $_POST['professor'] ?? $_POST['plantaoprofessor'] ?? $_GET['professor'] ?? $arrayNomes[0];
			}
			if (!in_array($profSelecionado, $arrayNomes, true)) {
				$profSelecionado = $arrayNomes[0];
			}

		echo '<input type="hidden" name="matricula" value="'.$matricula.'">';
		echo '<input type="hidden" name="disciplina" value="'.$disciplina.'">';

		echo '<label for="Professor">Professor(a): </label>';
		$lockedAttr = ($professorFixo !== null && !$isSecretaria) ? "data-locked=\"1\"" : "data-locked=\"0\"";
		echo "<select class=\"form-control\" name=\"professor\" id=\"id_professor_select\" $lockedAttr>";
		foreach($arrayNomes as $nome) {
			$selected = ($nome == $profSelecionado) ? "selected" : "";
			// quando já há professor fixo, só o original permanece habilitado (outros são visíveis, porém desabilitados)
			$optionDisabled = ($professorFixo !== null && $nome !== $professorFixo && !$isSecretaria) ? "disabled" : "";
			echo "<option value=\"$nome\" $selected $optionDisabled>$nome</option>";
		}
		echo '</select>';
		if ($professorFixo !== null && !$isSecretaria) {
			// garante envio do professor fixo e informa restrição
			echo "<input type=\"hidden\" name=\"professor\" value=\"$profSelecionado\">";
			echo "<div class=\"alert alert-info\" style=\"margin-top:8px;\">Você só pode editar plantões do professor escolhido neste período ($periodoAtual): <strong>$profSelecionado</strong>. As demais opções aparecem apenas para consulta.</div>";
		}

	echo '<br>';

	// Ocupação atual por horário (para checar lotação) - desconsidera vagas do próprio aluno em edição
	$ocupacao = array();
	if (!$isSecretaria) {
		$sqlOcupacao = "SELECT horario FROM alunosplantao WHERE professor = :prof AND matricula <> :mat";
		if ($temPeriodo) {
			$sqlOcupacao .= " AND periodo = :per";
		}
		$stmtOcupacao = $conexao->prepare($sqlOcupacao);
		$paramsOcupacao = [':prof' => $profSelecionado, ':mat' => $matricula];
		if ($temPeriodo) {
			$paramsOcupacao[':per'] = $periodoAtual;
		}
		$stmtOcupacao->execute($paramsOcupacao);
		$ocupRows = $stmtOcupacao->fetchAll(PDO::FETCH_ASSOC);
		foreach ($ocupRows as $row) {
			$key = $row['horario'];
			if (!isset($ocupacao[$key])) {
				$ocupacao[$key] = 0;
			}
			$ocupacao[$key]++;
		}
	}

	// Carrega datas bloqueadas do calendário principal (feriados, provas, reuniões, etc.)
	$mapMes = array(
		'jan' => 1, 'fev' => 2, 'mar' => 3, 'abr' => 4, 'mai' => 5, 'jun' => 6,
		'jul' => 7, 'ago' => 8, 'set' => 9, 'out' => 10, 'nov' => 11, 'dez' => 12
	);
	$datasBloqueadas = array();
	$sqlEventos = "SELECT dia, mes, ano FROM calendario WHERE destino = 'alunos' OR destino = 'global'";
	$queryEventos = $conexao->query($sqlEventos);
	if ($queryEventos !== false) {
		$resultEventos = $queryEventos->fetchAll(PDO::FETCH_ASSOC);
		foreach ($resultEventos as $ev) {
			$mesNum = isset($mapMes[$ev['mes']]) ? $mapMes[$ev['mes']] : null;
			if ($mesNum === null) {
				continue;
			}
			$dataEv = sprintf("%04d-%02d-%02d", $ev['ano'], $mesNum, $ev['dia']);
			$datasBloqueadas[$dataEv] = true;
		}
	}

		// Busca agenda do professor selecionado (primeiro por nome, depois por matrícula se necessário)
		$sqlProfAgenda = "SELECT * FROM horariosplantao WHERE nome = :nome";
		$stmtProfAgenda = $conexao->prepare($sqlProfAgenda);
		$stmtProfAgenda->execute([':nome' => $profSelecionado]);
		$agendaProf = $stmtProfAgenda->fetchAll(PDO::FETCH_ASSOC);
		if (count($agendaProf) === 0) {
			$matProfSel = PROFESSORES_GETMATRICULABYNAME($profSelecionado);
			if (!empty($matProfSel)) {
				$stmtProfAgenda = $conexao->prepare("SELECT * FROM horariosplantao WHERE matricula = :mat");
				$stmtProfAgenda->execute([':mat' => $matProfSel]);
				$agendaProf = $stmtProfAgenda->fetchAll(PDO::FETCH_ASSOC);
			}
		}

		if (count($agendaProf) === 0) {
			echo "<br><h4 class=\"text-center\">O professor selecionado ainda não tem horários de plantão cadastrados. Solicite à secretaria a inclusão da agenda.</h4>";
			return;
		}

		$dias = array("", "", "segunda", "terca", "quarta", "quinta", "sexta");
	$displaySlots = array();
	$seenSlots = array();
	$rangeInicio = null;
	$rangeFim = null;
	$cfgInicio = isset($PERIODO_PLANTAO_DATA_INICIO) ? trim((string)$PERIODO_PLANTAO_DATA_INICIO) : '';
	$cfgFim = isset($PERIODO_PLANTAO_DATA_FIM) ? trim((string)$PERIODO_PLANTAO_DATA_FIM) : '';
	if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $cfgInicio) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $cfgFim)) {
		$inicioTmp = DateTime::createFromFormat('Y-m-d', $cfgInicio);
		$fimTmp = DateTime::createFromFormat('Y-m-d', $cfgFim);
		if ($inicioTmp instanceof DateTime && $fimTmp instanceof DateTime && $inicioTmp <= $fimTmp) {
			$rangeInicio = $inicioTmp;
			$rangeFim = $fimTmp;
		}
	}
	// fallback legado, caso as datas não estejam configuradas corretamente
	if (!$rangeInicio || !$rangeFim) {
		$hoje = new DateTime();
		$anoBase = (int)$hoje->format('Y');
		$rangeInicio = new DateTime("$anoBase-03-16");
		$rangeFim = new DateTime("$anoBase-06-12");
		if ($hoje > $rangeFim) {
			$anoBase++;
			$rangeInicio = new DateTime("$anoBase-03-16");
			$rangeFim = new DateTime("$anoBase-06-12");
		}
	}

	for ($dataAtual = clone $rangeInicio; $dataAtual <= $rangeFim; $dataAtual->modify('+1 day')) {
		$dow = intval($dataAtual->format('N')) + 1; // segunda = 2

		if ($dow < 2 || $dow > 6) {
			continue; // só segunda a sexta
		}

		$dataSql = $dataAtual->format('Y-m-d');
		if (isset($datasBloqueadas[$dataSql])) {
			continue; // pula datas bloqueadas no calendário principal
		}

		foreach ($agendaProf as $plantao) {
			for ($slot = 1; $slot <= 3; $slot++) {
				$diaCampo = "dia$slot";
				$iniCampo = "ini$slot";
				$fimCampo = "fim$slot";
				$capCampo = "alunos$slot";

				if (!isset($plantao[$diaCampo]) || intval($plantao[$diaCampo]) !== $dow) {
					continue;
				}

				$start = intval($plantao[$iniCampo]);
				$end   = intval($plantao[$fimCampo]);
				$cap   = intval($plantao[$capCampo]) > 0 ? intval($plantao[$capCampo]) : 1;

				for ($hora = $start; $hora < $end; $hora++) {
					$horaFim = $hora + 1;

					$label = $dataAtual->format('d/m/Y') . " (" . $dias[$dow] . ") - ";
					$label .= sprintf("%02d:00 às %02d:00", $hora, $horaFim);

					$key = $dataSql . "|" . $dias[$dow] . " - " . sprintf("%02d:00 às %02d:00", $hora, $horaFim);
					if (isset($seenSlots[$key])) {
						continue; // evita duplicado
					}
					$seenSlots[$key] = true;
					$ocupados = isset($ocupacao[$key]) ? $ocupacao[$key] : 0;
					$disponiveis = max(0, $cap - $ocupados);

					$displaySlots[] = array(
						"value" => $key,
						"label" => $label,
						"disponiveis" => $disponiveis,
					);
				}
			}
		}
	}

		// Endpoint simples para AJAX de disponibilidade (retorna só JSON e encerra)
		if ($isStatusRequest) {
			$statusMap = array();
			foreach ($displaySlots as $slot) {
				$statusMap[$slot['value']] = $slot['disponiveis'];
			}
			header('Content-Type: application/json');
			echo json_encode($statusMap);
			return;
		}

		if (count($displaySlots) === 0) {
			echo "<br><h4 class=\"text-center\">Não há vagas abertas para este professor no período disponível.</h4>";
			return;
		}

	// Agrupa slots por data e depois por mês para exibir mini calendários mensais
	$slotsByDate = array();
	foreach ($displaySlots as $slot) {
		$parts = explode("|", $slot['value'], 2);
		if (count($parts) != 2) continue;
		$data = $parts[0];
		if (!isset($slotsByDate[$data])) {
			$slotsByDate[$data] = array();
		}
		$slotsByDate[$data][] = $slot;
	}
	ksort($slotsByDate);

	$mesNomes = array(
		1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Marco', 4 => 'Abril',
		5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
		9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
	);
	$weekdayHeaders = array('Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom');
	$months = array();

		foreach ($slotsByDate as $data => $slots) {
			$dt = new DateTime($data);
			$monthKey = $dt->format('Y-m');
			if (!isset($months[$monthKey])) {
				$monthStart = new DateTime($dt->format('Y-m-01'));
				$gridStart = clone $monthStart;
				$gridStart->modify('monday this week');
				$gridEnd = clone $gridStart;
				$gridEnd->modify('+41 day'); // 6 semanas fixas para manter caixas com mesma altura

				$mesNumero = intval($dt->format('n'));
				$months[$monthKey] = array(
					"label" => $mesNomes[$mesNumero]."/".$dt->format('Y'),
				"monthNum" => $dt->format('m'),
				"gridStart" => $gridStart->format('Y-m-d'),
				"gridEnd" => $gridEnd->format('Y-m-d'),
				"days" => array()
			);
		}

		usort($slots, function($a, $b) {
			$hA = 99;
			$hB = 99;
			if (preg_match('/(\\d{2}):00/', $a['value'], $mA)) {
				$hA = intval($mA[1]);
			}
			if (preg_match('/(\\d{2}):00/', $b['value'], $mB)) {
				$hB = intval($mB[1]);
			}
			if ($hA === $hB) {
				return strcmp($a['value'], $b['value']);
			}
			return $hA <=> $hB;
		});

		foreach ($slots as $slot) {
			$val = $slot['value'];
			$disp = isset($slot['disponiveis']) ? intval($slot['disponiveis']) : 0;
			$isChecked = in_array($val, $inscricaoHorarios);
			$parts = explode('|', $val, 2);
			$horaInfo = $parts[1] ?? '';
			$hourLabel = trim($horaInfo);
			if (strpos($horaInfo, ' - ') !== false) {
				$hourParts = explode(' - ', $horaInfo, 2);
				$hourLabel = trim($hourParts[1]);
			}
			if (!isset($months[$monthKey]["days"][$data])) {
				$months[$monthKey]["days"][$data] = array();
			}
			$months[$monthKey]["days"][$data][] = array(
				"value" => $val,
				"disp" => $disp,
				"checked" => $isChecked,
				"hourLabel" => $hourLabel
			);
		}
	}

	$monthKeys = array_keys($months);
	sort($monthKeys);

	?>

<style>
.month-group { display:none; padding:6px; border:1px solid #d9d9d9; border-radius:6px; background:#fff; margin-bottom:8px; box-shadow:0 4px 10px rgba(0,0,0,0.04); }
.month-group.active { display:flex; flex-direction:column; }
.months-track { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 260px)); gap:10px; align-items:stretch; justify-content:center; }
@media (max-width: 900px) {
	.months-track { grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); }
}
.month-title { text-align:center; font-weight:700; font-size:13px; color:#333; margin-bottom:6px; }
.month-grid-wrap { overflow-x:auto; display:flex; justify-content:center; flex:1; }
.month-grid { display:grid; grid-template-columns:repeat(7, minmax(28px, 32px)); gap:3px; min-width:0; }
.month-weekday { text-align:center; font-weight:700; color:#666; padding:1px 0; font-size:10px; }
.day-cell { border:1px solid #e3e3e3; border-radius:3px; background:#fff; min-height:28px; padding:0; display:flex; align-items:center; justify-content:center; transition:all .15s ease; }
.day-number { font-weight:700; margin:0; color:#222; font-size:11px; line-height:1; }
.day-outside { background:#f8f8f8; color:#b5b5b5; }
.day-outside .day-number { color:#c7c7c7; }
.day-status { display:none; }
.day-open { color:#2a7d46; }
.day-available { border-color:#86d5a3; background:#ecfaf2; cursor:pointer; }
.day-available:hover { border-color:#4caf72; box-shadow:0 0 0 1px #cfeedd inset; }
.day-active { border-color:#2a7d46; box-shadow:0 0 0 2px #b6e3c7 inset; }
.day-unavailable { opacity:.9; }

.day-panel { border:1px solid #d9d9d9; border-radius:8px; background:#fff; padding:14px; margin-top:4px; margin-bottom:20px; box-shadow:0 4px 10px rgba(0,0,0,0.04); }
.day-panel h4 { margin-top:0; margin-bottom:10px; }
.day-panel-hint { color:#666; margin-bottom:8px; }
.day-slot-group { display:none; }
.day-slot-group.active { display:block; }
.day-slot-list { display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:8px; }
.slot-item { font-size:12px; line-height:1.35; border:1px solid #e9e9e9; border-radius:6px; padding:8px; background:#fafafa; }
.slot-item label { margin:0; display:flex; align-items:flex-start; gap:6px; cursor:pointer; }
.slot-item input[type="checkbox"] { margin-top:2px; }
.slot-text { font-size:12px; }
.slot-full { color:#a00; font-weight:bold; }
.slot-disabled { color:#999; }
</style>

<?php
$monthIndex = 0;
echo "<div class=\"months-track\">";
foreach ($monthKeys as $mKey) {
	$monthData = $months[$mKey];
	$groupClass = "month-group active";
	echo "<div class=\"$groupClass\" data-month=\"$monthIndex\" data-label=\"".htmlspecialchars($monthData['label'], ENT_QUOTES)."\">";
	echo "<div class=\"month-title\">".htmlspecialchars($monthData['label'], ENT_QUOTES)."</div>";
	echo "<div class=\"month-grid-wrap\">";
	echo "<div class=\"month-grid\">";
	foreach ($weekdayHeaders as $wd) {
		echo "<div class=\"month-weekday\">$wd</div>";
	}

	$monthNum = $monthData['monthNum'];
	$gridStart = new DateTime($monthData['gridStart']);
	$gridEnd = new DateTime($monthData['gridEnd']);
	for ($cursor = clone $gridStart; $cursor <= $gridEnd; $cursor->modify('+1 day')) {
		$dateIso = $cursor->format('Y-m-d');
		$inMonth = ($cursor->format('m') === $monthNum);
		$slotsDia = $monthData['days'][$dateIso] ?? array();
		$temDisponivel = false;
		if ($inMonth) {
			foreach ($slotsDia as $slotDia) {
				if ($isSecretaria || intval($slotDia['disp']) > 0 || !empty($slotDia['checked'])) {
					$temDisponivel = true;
					break;
				}
			}
		}
		$isSelectable = ($inMonth && $temDisponivel);
		$cellClass = 'day-cell';
		if (!$inMonth) {
			$cellClass .= ' day-outside';
		} elseif ($isSelectable) {
			$cellClass .= ' day-available';
		} else {
			$cellClass .= ' day-unavailable';
		}

		echo "<div class=\"$cellClass\" data-date=\"$dateIso\" data-inmonth=\"".($inMonth ? "1" : "0")."\" data-selectable=\"".($isSelectable ? "1" : "0")."\">";
		echo "<div class=\"day-number\">".$cursor->format('d')."</div>";
		echo "</div>";
	}

	echo "</div>";
	echo "</div>";
	echo "</div>";
	$monthIndex++;
}
echo "</div>";

echo "<div id=\"daySlotsPanel\" class=\"day-panel\">";
echo "<h4 id=\"daySlotsTitle\">Selecione um dia disponivel no calendario</h4>";
echo "<div id=\"daySlotsHint\" class=\"day-panel-hint\">Clique em um dia verde para abrir os horarios daquele dia.</div>";
echo "<div id=\"daySlotsGroups\">";
foreach ($slotsByDate as $dataIso => $slotsDia) {
	$dtSlot = new DateTime($dataIso);
	$groupLabel = $dtSlot->format('d/m/Y');
	echo "<div class=\"day-slot-group\" data-date=\"".htmlspecialchars($dataIso, ENT_QUOTES)."\" data-label=\"$groupLabel\">";
	echo "<div class=\"day-slot-list\">";
	foreach ($slotsDia as $slot) {
		$val = htmlspecialchars($slot['value'], ENT_QUOTES);
		$disp = intval($slot['disponiveis']);
		$isChecked = in_array($slot['value'], $inscricaoHorarios);
		$checkedAttr = $isChecked ? "checked" : "";
		$disabledAttr = ($disp <= 0 && !$isChecked && !$isSecretaria) ? "disabled" : "";
		$slotKey = $val;
		$parts = explode('|', $slot['value'], 2);
		$horaInfo = $parts[1] ?? '';
		$hourLabel = trim($horaInfo);
		if (strpos($horaInfo, ' - ') !== false) {
			$hourParts = explode(' - ', $horaInfo, 2);
			$hourLabel = trim($hourParts[1]);
		}
		$hourLabelEsc = htmlspecialchars($hourLabel, ENT_QUOTES);
		$dispText = ($disp <= 0 && !$isChecked && !$isSecretaria) ? "$hourLabelEsc - HORARIO LOTADO" : "$hourLabelEsc - {$disp} vagas";
		$itemClass = ($disp <= 0 && !$isChecked && !$isSecretaria) ? "slot-item slot-full" : "slot-item";
		echo "<div class=\"$itemClass\">";
		echo "<label>";
		echo "<input type=\"checkbox\" name=\"escolha[]\" value=\"$val\" class=\"slotCheck\" data-date=\"".htmlspecialchars($dataIso, ENT_QUOTES)."\" data-slotkey=\"$slotKey\" data-hour=\"$hourLabelEsc\" data-disp=\"$disp\" $checkedAttr $disabledAttr>";
		echo "<span class=\"slot-text\">{$dispText}</span>";
		echo "</label>";
		echo "</div>";
	}
	echo "</div>";
	echo "</div>";
}
echo "</div>";
echo "</div>";
?>

	<div class="well" id="selectedSummary" style="margin-top:30px;">
		<strong>Plantões selecionados (<span id="selectedCount">0</span>/5): <?php echo $profSelecionado; ?></strong>
		<ul id="selectedList" style="margin-top:15px; list-style: none; padding-left: 0; line-height: 1.8;"></ul>
	</div>

	<br>
	<div style="max-width: 320px;">
		<?php
			$targetAction = "cadastrarplantao.php";
			if ($isSecretaria) {
				$targetAction = "../secretaria/cadastrarplantao_secretaria.php";
			}
		?>
		<button type="submit" id="id_confirmar_plantoes" class="btn btn-primary btn-lg btn-block" formaction="<?php echo $targetAction; ?>" disabled>Confirmar Escolha de Plantões</button>
	</div>

<?php
		// Fecha wrapper iniciado antes
		if (!$isStatusRequest && !$isSecretaria) {
			echo '</div>';
		}
?>

	<script>
(function() {
	let checks = document.querySelectorAll('.slotCheck');
	const months = Array.from(document.querySelectorAll('.month-group'));
	const dayCells = Array.from(document.querySelectorAll('.day-cell[data-date]'));
	const daySlotGroups = Array.from(document.querySelectorAll('.day-slot-group'));
	const daySlotsTitle = document.getElementById('daySlotsTitle');
	const daySlotsHint = document.getElementById('daySlotsHint');
	const selectedCount = document.getElementById('selectedCount');
	const selectedList = document.getElementById('selectedList');
	const professorSelect = document.getElementById('id_professor_select');
	const btnConfirmar = document.getElementById('id_confirmar_plantoes');
	const isSecretaria = <?php echo $isSecretaria ? 'true' : 'false'; ?>;
	let activeDate = null;
	const slotGroupMap = new Map();
	daySlotGroups.forEach(group => {
		slotGroupMap.set(group.dataset.date, group);
	});

	function getVisibleMonthNodes() {
		return months;
	}

	function clearActiveDay() {
		activeDate = null;
		dayCells.forEach(cell => cell.classList.remove('day-active'));
		daySlotGroups.forEach(group => group.classList.remove('active'));
		if (daySlotsTitle) {
			daySlotsTitle.textContent = 'Selecione um dia disponivel no calendario';
		}
		if (daySlotsHint) {
			daySlotsHint.style.display = 'block';
		}
	}

	function setActiveDay(dateIso) {
		const visibleMonths = getVisibleMonthNodes();
		if (visibleMonths.length === 0) return;

		let targetCell = null;
		visibleMonths.forEach(monthNode => {
			if (targetCell) return;
			const candidate = monthNode.querySelector(`.day-cell[data-inmonth="1"][data-date="${dateIso}"]`);
			if (candidate) {
				targetCell = candidate;
			}
		});

		if (!targetCell || targetCell.dataset.selectable !== '1') {
			return;
		}

		activeDate = dateIso;
		dayCells.forEach(cell => {
			cell.classList.toggle('day-active', cell === targetCell);
		});
		daySlotGroups.forEach(group => {
			group.classList.toggle('active', group.dataset.date === dateIso);
		});

		if (daySlotsTitle) {
			const group = slotGroupMap.get(dateIso);
			const label = group ? group.dataset.label : dateIso;
			daySlotsTitle.textContent = `Horarios de ${label}`;
		}
		if (daySlotsHint) {
			daySlotsHint.style.display = 'none';
		}
	}

	function selectFirstAvailableDayInVisibleMonths() {
		const visibleMonths = getVisibleMonthNodes();
		if (visibleMonths.length === 0) {
			clearActiveDay();
			return;
		}

		if (activeDate) {
			let activeVisible = false;
			visibleMonths.forEach(monthNode => {
				if (activeVisible) return;
				const found = monthNode.querySelector(`.day-cell[data-inmonth="1"][data-date="${activeDate}"][data-selectable="1"]`);
				if (found) {
					activeVisible = true;
				}
			});
			if (activeVisible) {
				setActiveDay(activeDate);
				return;
			}
		}

		let firstAvailable = null;
		visibleMonths.forEach(monthNode => {
			if (firstAvailable) return;
			firstAvailable = monthNode.querySelector('.day-cell[data-inmonth="1"][data-selectable="1"]');
		});
		if (firstAvailable) {
			setActiveDay(firstAvailable.dataset.date);
		} else {
			clearActiveDay();
		}
	}

	function refreshDayAvailability() {
		const visibleMonths = getVisibleMonthNodes();
		dayCells.forEach(day => {
			if (day.dataset.inmonth !== '1') return;
			const dateIso = day.dataset.date;
			const group = slotGroupMap.get(dateIso);
			const checkboxes = group ? Array.from(group.querySelectorAll('.slotCheck')) : [];
			const hasAnySlot = checkboxes.length > 0;
			const hasAvailable = checkboxes.some(ch => {
				const disp = parseInt(ch.dataset.disp || '0', 10);
				return isSecretaria || ch.checked || disp > 0;
			});

			day.dataset.selectable = hasAvailable ? '1' : '0';
			day.classList.toggle('day-available', hasAvailable);
			day.classList.toggle('day-unavailable', !hasAvailable);

			const status = day.querySelector('.day-status');
			if (status) {
				if (hasAvailable) {
					status.textContent = 'Disponivel';
					status.classList.add('day-open');
				} else if (hasAnySlot) {
					status.textContent = 'Sem vaga';
					status.classList.remove('day-open');
				} else {
					status.textContent = 'Sem plantao';
					status.classList.remove('day-open');
				}
			}
		});

		if (visibleMonths.length === 0) {
			clearActiveDay();
			return;
		}

		if (activeDate) {
			let activeVisible = false;
			visibleMonths.forEach(monthNode => {
				if (activeVisible) return;
				const activeCell = monthNode.querySelector(`.day-cell[data-inmonth="1"][data-date="${activeDate}"][data-selectable="1"]`);
				if (activeCell) {
					activeVisible = true;
				}
			});
			if (!activeVisible) {
				selectFirstAvailableDayInVisibleMonths();
			}
		}
	}

	function updateSelected() {
		const selecionados = Array.from(checks).filter(c => c.checked);
		selectedCount.textContent = selecionados.length;
		selectedList.innerHTML = '';
		selecionados.forEach(c => {
			const li = document.createElement('li');
			const parts = c.value.split('|');
			const dataIso = parts[0] || '';
			const horario = parts[1] || '';
			let dataBR = dataIso;
			if (dataIso.includes('-')) {
				const [y,m,d] = dataIso.split('-');
				dataBR = `${d}/${m}/${y}`;
			}
			// Extrai dia da semana do label (ex: "quarta" dentro do horário)
			let diaSemana = '';
			let horarioTexto = horario;
			if (horario.includes('-')) {
				diaSemana = horario.split('-')[0].trim();
				horarioTexto = horario.split('-')[1].trim();
			}
			const dataCurta = dataBR.slice(0,5); // dd/mm
			const texto = `${dataCurta} - ${diaSemana} - ${horarioTexto}`;
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.textContent = '×';
			btn.className = 'btn btn-xs btn-danger';
			btn.style.marginRight = '6px';
			btn.style.padding = '2px 6px';
			btn.addEventListener('click', () => {
				c.checked = false;
				refreshDayAvailability();
				updateSelected();
			});

			li.textContent = '';
			li.appendChild(btn);
			li.append(texto);
			selectedList.appendChild(li);
		});

		if (btnConfirmar) {
			btnConfirmar.disabled = selecionados.length !== 5;
		}
	}

	function bindChecks() {
		checks = document.querySelectorAll('.slotCheck');
		checks.forEach(ch => {
			ch.addEventListener('change', () => {
				const selecionados = Array.from(checks).filter(c => c.checked);
				if (selecionados.length > 5) {
					ch.checked = false;
					alert('Selecione no máximo 5 plantões.');
				}
				refreshDayAvailability();
				updateSelected();
			});
		});
	}

	function refreshDisponibilidade() {
		fetch(`modes/mode_plantao.php?slotstatus=1&professor=${encodeURIComponent('<?php echo $profSelecionado; ?>')}&matricula=${encodeURIComponent('<?php echo $matricula; ?>')}`)
			.then(r => r.text())
			.then(txt => {
				let data = {};
				try {
					data = JSON.parse(txt);
				} catch (e) {
					console.warn('Não foi possível atualizar vagas (resposta inesperada).', e);
					return;
				}
				const disponibilidade = data || {};
				document.querySelectorAll('.slotCheck').forEach(ch => {
					const key = ch.dataset.slotkey;
					if (!key || !(key in disponibilidade)) return;
					const disp = disponibilidade[key];
					const span = ch.parentNode.querySelector('.slot-text');
					const hourLabel = ch.dataset.hour || '';
					ch.dataset.disp = disp;
					if (disp <= 0 && !ch.checked && !<?php echo $isSecretaria ? 'true' : 'false'; ?>) {
						ch.disabled = true;
						if (span) span.textContent = hourLabel ? `${hourLabel} - HORARIO LOTADO` : 'HORARIO LOTADO';
						const slotItem = ch.closest('.slot-item');
						if (slotItem) slotItem.classList.add('slot-full');
					} else if (span) {
						ch.disabled = false;
						span.textContent = hourLabel ? `${hourLabel} - ${disp} vagas` : `${disp} vagas`;
						const slotItem = ch.closest('.slot-item');
						if (slotItem) slotItem.classList.remove('slot-full');
					}
				});
				refreshDayAvailability();
				updateSelected();
			})
			.catch(() => {});
	}

	dayCells.forEach(cell => {
		if (cell.dataset.inmonth !== '1') return;
		cell.addEventListener('click', () => {
			if (cell.dataset.selectable !== '1') return;
			setActiveDay(cell.dataset.date);
		});
	});

	// Submete troca de professor mantendo modo listaplantao
	if (professorSelect) {
		professorSelect.addEventListener('change', () => {
			if (professorSelect.dataset.locked === '1' && !<?php echo $isSecretaria ? 'true' : 'false'; ?>) {
				// bloqueia troca, mas mantém dropdown visível
				professorSelect.value = '<?php echo $profSelecionado; ?>';
				return;
			}
			const form = professorSelect.form;
			// garante que a matrícula viaje junto na submissão (secretaria)
			let hidMat = document.createElement('input');
			hidMat.type = 'hidden';
			hidMat.name = 'matricula';
			hidMat.value = '<?php echo $matricula; ?>';
			form.appendChild(hidMat);

			let hiddenMode = document.createElement('input');
			hiddenMode.type = 'hidden';
			hiddenMode.name = 'mode';
			hiddenMode.value = <?php echo $isSecretaria ? "'inscricaoPlantaoSecretaria'" : "'listaplantao'"; ?>;
			form.appendChild(hiddenMode);
			form.submit();
		});
	}

	// Limita a seleção a 5 itens
	bindChecks();
	refreshDayAvailability();
	selectFirstAvailableDayInVisibleMonths();

	updateSelected();
	// Revalida disponibilidade a cada 30s para refletir novas inscrições
	setInterval(refreshDisponibilidade, 30000);
})();
</script>

<?php

	function grupoDisciplinaEquivalentePlantao($disciplina)
	{
		$disciplina = strtoupper(trim((string)$disciplina));
		if ($disciplina === 'JUR1971' || $disciplina === 'JUR1991') {
			return 'JUR1971/1991';
		}
		if ($disciplina === 'JUR1972' || $disciplina === 'JUR1992') {
			return 'JUR1972/1992';
		}
		return $disciplina;
	}

	function extraiGruposDisciplinaPlantao($valorDisciplina)
	{
		$valorDisciplina = trim((string)$valorDisciplina);
		if ($valorDisciplina === '') {
			return array();
		}
		$valorDisciplina = str_replace(array(',', ';'), '$', $valorDisciplina);
		$partes = array_filter(array_map('trim', explode('$', $valorDisciplina)));
		$grupos = array();
		foreach ($partes as $parte) {
			$grupo = grupoDisciplinaEquivalentePlantao($parte);
			if ($grupo === '') {
				continue;
			}
			$grupos[$grupo] = true;
		}
		return array_keys($grupos);
	}

	function disciplinasCompativeisPlantao($disciplinaAluno, $disciplinaPlantao)
	{
		$gruposAluno = extraiGruposDisciplinaPlantao($disciplinaAluno);
		$gruposPlantao = extraiGruposDisciplinaPlantao($disciplinaPlantao);

		// Se faltar informação em qualquer lado, mantém comportamento permissivo legado.
		if (count($gruposAluno) === 0 || count($gruposPlantao) === 0) {
			return true;
		}

		return count(array_intersect($gruposAluno, $gruposPlantao)) > 0;
	}

	function verificaDisponibilidade($resultPlantoes, $disciplinaAluno, $meuProfessor)
	{
		// usa conexão global compartilhada
		global $conexao;
		include_once($_SERVER['DOCUMENT_ROOT'].'/teste/utils/professores.php');

		$accept = false;
		$dia1 = $resultPlantoes['dia1']; // mantido apenas por compatibilidade; não bloqueia mais a lista
		$matMeuProfessor = PROFESSORES_GETMATRICULABYNAME($meuProfessor);
		// fallback: se ainda vazio, tenta pelo campo professor da tabela alunos (matricula informada)
		if (empty($matMeuProfessor)) {
			$sqlFallback = "SELECT professor FROM alunos WHERE matricula = :mat LIMIT 1";
			$stmtFallback = $conexao->prepare($sqlFallback);
			$stmtFallback->execute([':mat' => $GLOBALS['matricula'] ?? 0]);
			$rowFb = $stmtFallback->fetch(PDO::FETCH_ASSOC);
			if ($rowFb && isset($rowFb['professor'])) {
				$matMeuProfessor = PROFESSORES_GETMATRICULABYNAME($rowFb['professor']);
			}
		}

		// Whitelist fixa por professor (fallback caso permissoesEspeciais não esteja populada)
		$whitelistProfessor = [
			'BEATRIZ DA SILVA ROLAND' => [
				'ADRIANO BARCELOS ROMEIRO',
				'BRUNO MACHADO EIRAS',
				'EVANDRO SOUZA E LIMA',
				'PEDRO MATIAS DA COSTA FILHO',
				'RAFAEL DA MOTA MENDONCA',
			],
		];

		// Lista de professores permitidos (começa com o próprio) via tabela permissoesEspeciais
		$permitidosMat = [$matMeuProfessor];
		$stmtPerm = $conexao->prepare("SELECT permitido FROM permissoesEspeciais WHERE permissor = :perm");
		$stmtPerm->execute([':perm' => $matMeuProfessor]);
		$permitidosMat = array_merge($permitidosMat, $stmtPerm->fetchAll(PDO::FETCH_COLUMN));

		// Fallback manual para Beatriz (se a tabela não estiver populada)
		$manualLiberacoes = [
			'BEATRIZ DA SILVA ROLAND' => [
				'ADRIANO BARCELOS ROMEIRO',
				'BRUNO MACHADO EIRAS',
				'EVANDRO SOUZA E LIMA',
				'PEDRO MATIAS DA COSTA FILHO',
				'RAFAEL DA MOTA MENDONCA',
			],
		];
		$meuProfessorUpper = strtoupper(trim($meuProfessor));
		if (isset($manualLiberacoes[$meuProfessorUpper])) {
			foreach ($manualLiberacoes[$meuProfessorUpper] as $nomeLib) {
				$matLib = PROFESSORES_GETMATRICULABYNAME($nomeLib);
				if (!empty($matLib)) {
					$permitidosMat[] = $matLib;
				}
			}
		}

		// normaliza para evitar falhas por espaços ou tipos mistos
		$permitidosMat = array_unique(array_filter(array_map('trim', $permitidosMat)));
		$matPlantao = trim((string)($resultPlantoes['matricula'] ?? ''));

		// Garante segurança contra valores nulos
		$disciplinaAluno = $disciplinaAluno ?? '';
		$disciplinaClasse = $resultPlantoes['disciplina'] ?? '';
		$disciplinaCompativel = disciplinasCompativeisPlantao($disciplinaAluno, $disciplinaClasse);

		// Primeiro, tenta permissão direta vinda da tabela (ou o próprio professor)
		if ($disciplinaCompativel && in_array($matPlantao, $permitidosMat, true)) {
			$accept = true;
		}

		// Whitelist fixa por professor (fallback quando permissoesEspeciais não está populada)
		if (!$accept && $disciplinaCompativel && isset($whitelistProfessor[strtoupper(trim($meuProfessor))])) {
			$permitidosNomes = $whitelistProfessor[strtoupper(trim($meuProfessor))];
			if (in_array(strtoupper(trim($resultPlantoes['nome'])), array_map('strtoupper', $permitidosNomes))) {
				$accept = true;
			}
		}

		// Verifica permissões especiais entre professor do aluno e do plantão (tabela)
		if (!$accept && $disciplinaCompativel) {
			$accept = trataPermissoesEspeciais($matMeuProfessor, $matPlantao);
		}

		// Regra específica: alunos de JUR1974 com professor Alexandre Servino Assed só veem o próprio professor,
		// salvo se houver permissão especial explicitamente cadastrada.
		$alexRule = (
			strtoupper(trim($disciplinaAluno)) === 'JUR1974' &&
			strtoupper(trim($meuProfessor)) === 'ALEXANDRE SERVINO ASSED'
		);
		if ($alexRule && $matPlantao != $matMeuProfessor && !trataPermissoesEspeciais($matMeuProfessor, $matPlantao)) {
			$accept = false;
		}

		return $accept;
	}


	function trataPermissoesEspeciais($matMeuProfessor, $matOutroProfessor)
	{

		$ret = false;
		$conexao = $GLOBALS['conexao'];
		$sqlPermissao = "SELECT * FROM permissoesEspeciais WHERE `permissor` = \"$matMeuProfessor\" AND `permitido` = \"$matOutroProfessor\"";
		$queryPermissao = $conexao->query($sqlPermissao);
		if($queryPermissao != false)
			if(count($queryPermissao->fetchAll(PDO::FETCH_ASSOC)) > 0)
				$ret = true;
	
		return $ret;
	}
?>
