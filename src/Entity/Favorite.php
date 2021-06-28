<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\DefaultEntity;
use App\Utils\Validator;

/**
 * Favorite
 *
 * @ORM\Table(name="public.favorite_repository")
 * @ORM\Entity
 * @ORM\ChangeTrackingPolicy("DEFERRED_EXPLICIT")
 */
class Favorite extends DefaultEntity {
	/**
	 * @var int
	 *
	 * @ORM\Column(name="id", type="integer", nullable=false)
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="SEQUENCE")
	 * @ORM\SequenceGenerator(sequenceName="public.favorite_repository_seq", allocationSize=1, initialValue=1)
	 */
	private $id;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="name", type="string")
	 */
	private $name;
	
	/**
	 * @var string
	 *
	 * @ORM\Column(name="owner", type="string")
	 */
	private $owner;

	/**
	 * @var string|null
	 *
	 * @ORM\Column(name="html_url", type="string")
	 */
	private $htmlUrl;

	/**
	 * @var bool
	 *
	 * @ORM\Column(name="active", type="boolean", nullable=false)
	 */
	private $active = true;

	public function getOrmInfo(): array {
		return [
			'id' => [
				'pk' => true,
				'columnName' => 'id',
				'columnType' => 'integer',
				'length' => null,
				'precision' => null,
				'scale' => null,
				'nullable' => false,
				'validationRule' => ['type' => Validator::INTEGER, 'requireFilled' => true]
			],
			'name' => [
				'pk' => false,
				'columnName' => 'name',
				'columnType' => 'string',
				'length' => null,
				'precision' => null,
				'scale' => null,
				'nullable' => false,
				'validationRule' => ['type' => Validator::STRING, 'requireFilled' => true]
			],
			'owner' => [
				'pk' => false,
				'columnName' => 'owner',
				'columnType' => 'string',
				'length' => null,
				'precision' => null,
				'scale' => null,
				'nullable' => false,
				'validationRule' => ['type' => Validator::STRING, 'requireFilled' => true]
			],
			'htmlUrl' => [
				'pk' => false,
				'columnName' => 'external_code',
				'columnType' => 'string',
				'length' => null,
				'precision' => null,
				'scale' => null,
				'nullable' => true,
				'validationRule' => ['type' => Validator::STRING, 'requireFilled' => true]
			],
			'active' => [
				'pk' => false,
				'columnName' => 'active',
				'columnType' => 'boolean',
				'length' => null,
				'precision' => null,
				'scale' => null,
				'nullable' => false,
				'validationRule' => ['type' => Validator::BOOLEAN, 'requireFilled' => true]
			],
		];
	}

	public function getId(): int {
		return $this->id;
	}
	
	public function setId(int $id): self {
		$this->id = $id;
		return $this;
	}

	public function getName(): string {
		return $this->name;
	}
	
	public function setName(string $name): self {
		$this->name = $name;
		return $this;
	}

	public function getHtmlUrl(): ?string {
		return $this->htmlUrl;
	}
	
	public function setHtmlUrl(?string $htmlUrl): self {
		$this->htmlUrl = $htmlUrl;
		return $this;
	}

	public function getActive(): bool {
		return $this->active;
	}
	
	public function setActive(bool $active): self {
		$this->active = $active;
		return $this;
	}
	
	public function getOwner(): string {
		return $this->owner;
	}
	
	public function setOwner(string $owner): self {
		$this->owner = $owner;
		return $this;
	}

}