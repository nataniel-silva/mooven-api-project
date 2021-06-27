<?php
namespace App;

use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManager;
use App\Entity\EntityFactory;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\Bundle\DoctrineBundle\Registry;

interface DefaultInterface {
	public function setTranslator(TranslatorInterface $translator);
	public function setRegistry(RegistryInterface $doctrine);
	public function setEntityFactory(EntityFactory $ef);
	public function em(string $name = null): EntityManager;
	public function setDefaultEntityManagerName(string $name): void;
	public function getDefaultEntityManagerName(): ?string;
	public function begin(): void;
	public function commit(): void;
	public function rollback(): void;
	public function inTransaction(): bool;
	public function savepoint(string $savepoint): void;
	public function rollbackTo(string $savepoint): void;
}