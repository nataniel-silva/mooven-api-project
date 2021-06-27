<?php

namespace App\Controller;

use App\Business\FavoriteBO;
use App\Entity\Favorite;
use App\Utils\Validator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class FavoriteController extends DefaultController
{
	/**
	 * @Route("/favorite-repository/", name="favorite_repository.search", methods={"GET"})
	 */
	public function searchFavorites() {
		$rules = [
			'name' => ['type' => Validator::STRING],
			'htmlUrl' => ['type' => Validator::STRING],
		];

		dump('211');

		return $this->searchRequest(
			[$this->ef->getRepo(Favorite::class), 'search'], 'f', $rules,
			false,[],true,'f'
		);
	}
	
	private function saveFavorite(bool $isInsert, FavoriteBO $favoriteBO) {
		$rules = [
			'name' => ['type' => Validator::STRING, 'requireFilled' => true],
			'htmlUrl' => ['type' => Validator::STRING, 'requireFilled' => true],
			'active' => ['type' => Validator::BOOLEAN, 'default' => true],
		];
		
		if (!$isInsert) {
			$rules['id'] = ['type' => Validator::INTEGER, 'requireFilled' => true, 'from' => self::URL];
		}
		
		$data = $this->validateRequest($rules, false);
		
		try {
			$this->begin();
			$favorite = $favoriteBO->saveActorFromArray($data);
			$this->commit();
			return $this->generateResponse($favorite->toArray());
		} catch (\Exception $e) {
			$this->rollback();
			throw $e;
		}
	}
	
	/**
	 * @Route("/favorite-repository/", name="favorite_repository.insert", methods={"POST"})
	 */
	public function insertFavorite(FavoriteBO $favoriteBO) {
		return $this->saveFavorite(true, $favoriteBO);
	}
	/**
	 * @Route("/favorite-repository/{id<\d+>}", name="favorite_repository.update", methods={"POST"})
	 */
	public function updateFavorite(FavoriteBO $favoriteBO) {
		return $this->saveFavorite(false, $favoriteBO);
	}

}