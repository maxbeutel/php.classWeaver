<?php

namespace ClassWeaver\Weaver;

class Result
{
	private $weavedClassesMap;

	private $weavedClassesCount;

	public function __construct($weavedClassesMap, $weavedClassesCount)
	{
		$this->weavedClassesMap = $weavedClassesMap;
		$this->weavedClassesCount = $weavedClassesCount;
	}

	public function getWeavedClassesMap()
	{
		return $this->weavedClassesMap;
	}

	public function getWeavedClassesCount()
	{
		return $this->weavedClassesCount;
	}
}