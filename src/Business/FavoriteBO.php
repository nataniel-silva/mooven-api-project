<?php

namespace App\Business;

use App\Entity\Common\Favorite;
use App\Exception\BOException;

class FavoriteBO implements BOInterface {
	use BOTrait;

	/**
	 * @param array $favoriteData
	 * @return \App\Entity\DefaultEntity
	 * @throws \Exception
	 */
	public function saveActorFromArray(array $actorData): Favorite {
		$isUpdate = $actorData['id'] ?? false;
		
		if (!$isUpdate) {
			if (empty($actorData['active'])) {
				$actorData['active'] = true;
			}
		}

		$fieldsUpdate = ['active'];
		return $this->saveEntityFromArray($actorData, $fieldsUpdate, Favorite::class);
	}

}