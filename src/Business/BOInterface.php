<?php
namespace App\Business;


use App\Utils\Validator;
use App\DefaultInterface;

interface BOInterface extends DefaultInterface {
	public function setValidator(Validator $validator);
	public function getErrors(): array;
}