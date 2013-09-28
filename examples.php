<?php

include 'src/Reece45/Debug/MemoryAnalyzer.php';

class ExampleClass
{
    public $a = '2342';
    public $b;
}

$example = new ExampleClass();
$example->b = $example;
$example->c = array($example, $example);

$usefulNumber = Reece45\Debug\MemoryAnalyzer::analyze($example);
printf("\$example memory anylsis: %s\n", $usefulNumber);

Reece45\Debug\MemoryAnalyzer::printChildrenAnalysis($example);
