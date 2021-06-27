<?php
namespace App\Command;

use App\Business\MailBO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Utils\Utils;

abstract class DefaultCommand extends Command {
	/**
	 * Lista de jobs disponíveis com o respectivo método e parâmetros para execução
	 * @var array
	 */
	private $_jobs = [];
	
	/**
	 * @var MailBO
	 */
	protected $mailBO;
	
	public function __construct(MailBO $mailBO) {
		$this->mailBO = $mailBO;
		parent::__construct();
	}
	
	/**
	 * {@inheritdoc}
	 */
	protected function configure() {
		$this->addArgument('job', InputArgument::REQUIRED, 'O job que deve ser executado. Jobs disponíveis: '.$this->_getJobsList());
		$this->addArgument('params', InputArgument::IS_ARRAY, 'Parâmetros que podem ser passados para execução do job.');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$job = $input->getArgument('job');
		$run = $this->_validateJob($job);
		$jobInfo = [
			'job' => $job, 'params' => $run['params'],
		];
		$params = $input->getArgument('params');
		if (!empty($params)) {
			$run['params'] = $params;
		}
		
		try {
			call_user_func_array($run['call'], $run['params']);
		} catch (\Throwable $e) {
			$jobInfo['exception'] = $e;
			$this->_sendErrorEmail($jobInfo);
			throw $e;
		}
	}
	
	/**
	 * Envia um email informando erro inesperado que ocorreu
	 * @param array $info Array com todas as informações que deseja enviar no email
	 */
	private function _sendErrorEmail(array $info) {
		$this->mailBO->sendErrorEmail(
			'Ocorreu erro no job "'.$info['job'].'", ao executar com os parâmetros:<br>'
			.Utils::dumpString($info['params'])
			.'Comunique aos desenvolvedores para que verifiquem o ocorrido<br><br>'
			.'Exception:<br>'.Utils::dumpString($info['exception']),
			'Erro no job "'.$info['job'].'"'
		);
	}
	
	/**
	 * @param array onde o índice é o nome do job, e o valor é um array com:
	 * [
	 *   'call' => callable que deve ser chamado para executar o job
	 *   'params' => array com os parâmetros que deve ser passados para o 'method'
	 * ]
	 * @return self
	 */
	protected function setJobs(array $jobs): self {
		$this->_jobs = $jobs;
		return $this;
	}
	protected function getJobs() {
		return $this->_jobs;
	}

	private function _validateJob($job): array {
		if (!isset($this->_jobs[$job])) {
			throw new RuntimeException('Job inválido. Valores possíveis: '.$this->_getJobsList());
		}
		return $this->_jobs[$job];
	}
	
	private function _getJobsList() {
		return implode(', ', array_keys($this->getJobs()));
	}
}