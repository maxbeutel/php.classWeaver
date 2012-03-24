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


use PHPParser_NodeVisitorAbstract;
use PHPParser_Node;
use PHPParser_Node_Stmt_ClassMethod;
use PHPParser_Node_Stmt_Class;
use PHPParser_Node_Name;
use PHPParser_Node_Expr_Array;

class MyNodeVisitor extends PHPParser_NodeVisitorAbstract
{
	const WEAVED_METHOD_PREFIX = '__MB_WEAVED__';
	
	private $methodsToWeave = array();
	
	public function __construct() 
	{
	}
	
	public function leaveNode(PHPParser_Node $node) {
		if ($node instanceof PHPParser_Node_Stmt_ClassMethod && $node->type === PHPParser_Node_Stmt_Class::MODIFIER_PUBLIC) {
			$signature = $arguments = $interceptedArguments = array();
			
			if (count($node->params) > 0) {
				foreach ($node->params as $param) {
					$signature[] = sprintf(
						'%s %s$%s %s',
						($param->type ? ($param->type instanceof PHPParser_Node_Name && $param->type->isFullyQualified() ? '\\' . $param->type : $param->type) : ''),                        
						($param->byRef ? '&' : ''),
						$param->name,
						($param->default ? '=' . ($param->default instanceof PHPParser_Node_Expr_Array ? 'array()' : $param->default->name) : '')
					);
					
					print_r($param->default);
					
					$arguments[] = sprintf(
						'%s$%s',
						($param->byRef ? '&' : ''),
						$param->name
					);
					
					$interceptedArguments[] = sprintf(
						'"%s" => %s$%s',
						$param->name,
						($param->byRef ? '&' : ''),
						$param->name
					);
				}
			}
			
			$this->methodsToWeave[] = array(
				'originalName'  => $node->name,
				'weavedName'    => static::WEAVED_METHOD_PREFIX . $node->name,
				'docComment'    => $node->getDocComment(),
				'signature'     => join($signature, ', '),
				'arguments'     => join($arguments, ', '),
				'interceptedArguments' => 'array(' . join($interceptedArguments, ',') .  ')',
			);
			
			$node->name = static::WEAVED_METHOD_PREFIX . $node->name;
			$node->setDocComment('');
		}
		
		if ($node instanceof PHPParser_Node_Stmt_Class) {
			$subNodes = $node->getIterator()->getArrayCopy();
			
			foreach ($this->methodsToWeave as $methodToWeave) {
				$code = '<?php
class __MB_TMP__
{
	public function ' . $methodToWeave['originalName'] . '(' . $methodToWeave['signature'] . ')
	{
		$__MB_WEAVED__interceptedArguments = ' . $methodToWeave['interceptedArguments'] . ';
	 
		$__MB_WEAVED__result = $this->' . $methodToWeave['weavedName'] . '(' . $methodToWeave['arguments'] . ');
		
		return $__MB_WEAVED__result;
	}
}
';
				
				print $code;
				print PHP_EOL;

				$parser = new PHPParser_Parser();

				try {
					$stmts = $parser->parse(new PHPParser_Lexer($code));

					$weavedMethodNode = $stmts[0]->getIterator()->getArrayCopy();
					$weavedMethodNode = $weavedMethodNode['stmts'][0];
					
					if ($methodToWeave['docComment']) {
						$weavedMethodNode->setDocComment($methodToWeave['docComment']);
					}

					$subNodes['stmts'][] = $weavedMethodNode;	
				} catch (PHPParser_Error $e) {
					throw new RuntimeException('Error weaving method', $e->getCode(), $e);	
				}
			}

			
			//
			if (preg_match('#(.*)Bundle$#', $node->name)) {
				$code = '<?php
class __MB_TMP__
{
	public function getPath()
	{
		return __DIR__;
	}
}
';
				
				$parser = new PHPParser_Parser();

				try {
					$stmts = $parser->parse(new PHPParser_Lexer($code));

					$overridenMethod = $stmts[0]->getIterator()->getArrayCopy();
					$overridenMethod = $overridenMethod['stmts'][0];
					
					$subNodes['stmts'][] = $overridenMethod;                	
				} catch (PHPParser_Error $e) {
					throw new RuntimeException('Error trying to override Bunde::getPath() method', $e->getCode(), $e);
				}
			}
			//
			
			
			
			if ($node instanceof PHPParser_Node_Stmt_Class) {
				$this->weavedFilePath = '/tmp/' . $node->namespacedName->toString('/') . '.php';
				$this->weavedNamespacedClassName = $node->namespacedName->toString();
			}
			
			
			return new PHPParser_Node_Stmt_Class($node->name, $subNodes, $node->getLine(), $node->getDocComment());
		}
	}
}

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
			
			$parser = new PHPParser_Parser();
			
			$traverser = new PHPParser_NodeTraverser();
			$traverser->addVisitor(new PHPParser_NodeVisitor_NameResolver());
			
			$myVisitor = new MyNodeVisitor();
			$traverser->addVisitor($myVisitor);
			
			$prettyPrinter = new PHPParser_PrettyPrinter_Zend();
			
			try {
				$stmts = $parser->parse(new PHPParser_Lexer(file_get_contents($file)));
				$stmts = $traverser->traverse($stmts);
				
				$code = $prettyPrinter->prettyPrint($stmts);
				$code = str_replace('__DIR__', "'" . dirname($file) . "'", $code);
				
				print $code;
				print PHP_EOL;
				print PHP_EOL;
				print PHP_EOL;

				#if (!file_exists(dirname($myVisitor->weavedFilePath))) {
				# mkdir(dirname($myVisitor->weavedFilePath), 0777, true);
				#}
				
				#file_put_contents($myVisitor->weavedFilePath, '<?php ' . $code);
				
				#$weavedClassesMap[$myVisitor->weavedNamespacedClassName] = $myVisitor->weavedFilePath;
			} catch (PHPParser_Error $e) {
				throw new RuntimeException('Parse Error in ' . $file, $e->getCode(), $e);
			}
			
			$i++;
		}

		var_dump($i);
	}
}