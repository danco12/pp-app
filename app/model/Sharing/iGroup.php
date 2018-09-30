<?php
namespace Group;

interface iGroup
{
	public function getPostLimit();
	public function getTimeIntervalBetweenPosts();
	public function getCasualnessNumber();
}