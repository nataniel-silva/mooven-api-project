<?php

namespace App\Business;

use App\Entity\Favorite;
use App\Exception\BOException;

class FavoriteBO implements BOInterface {
	use BOTrait;

	/**
	 * @param array $favoriteData
	 * @return \App\Entity\DefaultEntity
	 * @throws \Exception
	 */
	public function saveFavoriteFromArray(array $favoriteData): Favorite {
		$isUpdate = $favoriteData['id'] ?? false;
		
		$favorite = $this->ef->getRepo(Favorite::class)->findOneByConf([
			'alias' => 'f',
			'where' => 'f.name = :name AND f.owner = :owner',
			'params' => [':name' => $favoriteData['name'], ':owner' => $favoriteData['owner']]
		]);
		
		if (!empty($favorite)) {
			$favoriteData['id'] = $favorite->getId();
		}

		$fieldsUpdate = ['active'];
		return $this->saveEntityFromArray($favoriteData, $fieldsUpdate, Favorite::class);
	}

}