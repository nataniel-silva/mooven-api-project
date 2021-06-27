<?php
namespace App\Hydrator;

use Doctrine\ORM\Internal\Hydration\ObjectHydrator;

class CustomObjectHydrator extends ObjectHydrator {
	/**
	 * {@inheritdoc}
	 */
	protected function hydrateAllData() {
		$results = parent::hydrateAllData();
		
		foreach ($results as $indResult => $result) {
			$entity = $result[0];
			foreach ($result as $ind => $vl) {
				if ($ind === 0) {
					continue;
				}
				// Se o nome do campo começar com "_", então cada "_" representa o separador de path e vai setar o valor exatamente onde o caminho indicar
				if (substr($ind, 0, 1) === '_') {
					$path = explode('_', $ind);
					$maxInd = count($path) - 1;
					$auxEntity = $entity->{$path[1]};
					for ($indPath = 2; $indPath < $maxInd; $indPath++) {
						$auxEntity = $auxEntity->{$path[$indPath]};
					}
					$auxEntity->{$path[$indPath]} = $vl;
				} else {
					$entity->{$ind} = $vl;
				}
			}
			$results[$indResult] = $entity;
		}
		return $results;
	}
}