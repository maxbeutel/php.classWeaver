<?php

namespace ClassWeaver;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RecursiveRegexIterator;
use PHPParser_Parser;
use PHPParser_NodeTraverser;
use PHPParser_NodeVisitor_NameResolver;
use PHPParser_PrettyPrinter_Zend;
use PHPParser_Lexer;
use PHPParser_Error;
use RuntimeException;
use ClassWeaver\Weaver\Result;
use ClassWeaver\NodeVisitor\WeavingNodeVisitor;

class Weaver
{
	public function __construct()
	{
	}

	public function weaveFilesInDirectory($classesDirectory)
	{
		$directory = new RecursiveDirectoryIterator($classesDirectory);
		$iterator = new RecursiveIteratorIterator($directory);
		$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

		$weavedClassesMap = array();

		$i = 0;

		foreach ($regex as $file) {
			$file = $file[0];

			print '# weaving: ' . $file . PHP_EOL;
			
			$parser = new PHPParser_Parser();
			
			$traverser = new PHPParser_NodeTraverser();
			$traverser->addVisitor(new PHPParser_NodeVisitor_NameResolver());
			
			$myVisitor = new WeavingNodeVisitor();
			$traverser->addVisitor($myVisitor);
			
			$prettyPrinter = new PHPParser_PrettyPrinter_Zend();
			
			try {
				$stmts = $parser->parse(new PHPParser_Lexer(file_get_contents($file)));
				$stmts = $traverser->traverse($stmts);

				$code = $prettyPrinter->prettyPrint($stmts);
				$code = str_replace('__DIR__', "'" . dirname($file) . "'", $code);
				
//				print $code;
//				print PHP_EOL;

				if (!$myVisitor->weavingSucceeded()) {
					print '## weaving did not succeed, continue' . PHP_EOL;
					continue;
				}

				if (!file_exists(dirname($myVisitor->getWeavedFilePath()))) {
					mkdir(dirname($myVisitor->getWeavedFilePath()), 0777, true);
				}
				
				print '## dumping weaved class to ' . $myVisitor->getWeavedFilePath() . PHP_EOL;

				file_put_contents($myVisitor->getWeavedFilePath(), '<?php ' . $code);
				
				$weavedClassesMap[$myVisitor->getWeavedNamespacedClassName()] = $myVisitor->getWeavedFilePath();
			} catch (PHPParser_Error $e) {
				throw new RuntimeException('Parse Error in ' . $file, $e->getCode(), $e);
			}
			
			$i++;
		}

		return new Result($weavedClassesMap, $i);
	}
}