<?php

namespace ClassWeaver\NodeVisitor;

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
use PHPParser_NodeVisitorAbstract;
use PHPParser_Node;
use PHPParser_Node_Stmt_ClassMethod;
use PHPParser_Node_Stmt_Class;
use PHPParser_Node_Name;
use PHPParser_Node_Expr_Array;
use PHPParser_Node_Stmt_Interface;

class WeavingNodeVisitor extends PHPParser_NodeVisitorAbstract
{
	const WEAVED_METHOD_PREFIX = '__MB_WEAVED__';
	const TEMP_CLASS_NAME = '__MB_TMP__';
	const TEMP_PARAMETER_PREFIX = '__MB_WEAVED__';
	
	private $methodsToWeave = array();

	private $weavedFilePath;
	private $weavedNamespacedClassName;
	
	public function __construct() 
	{
	}

	public function getWeavedFilePath()
	{
		return $this->weavedFilePath;
	}

	public function getWeavedNamespacedClassName()
	{
		return $this->weavedNamespacedClassName;
	}

	public function weavingSucceeded()
	{
		return $this->weavedFilePath && $this->weavedNamespacedClassName;
	}
	
	private function weavePublicClassMethod(PHPParser_Node_Stmt_ClassMethod $node)
	{
		$signature = $arguments = $interceptedArguments = array();
		
		if (count($node->params) > 0) {
			foreach ($node->params as $param) {
				$defaultValue = '';

				if ($param->default) {
					$prettyPrinter = new PHPParser_PrettyPrinter_Zend();
					$defaultValue = $prettyPrinter->prettyPrintExpr($param->default);
				}

				$signature[] = sprintf(
					'%s %s$%s %s',
					($param->type ? ($param->type instanceof PHPParser_Node_Name && $param->type->isFullyQualified() ? '\\' . $param->type : $param->type) : ''),                        
					($param->byRef ? '&' : ''),
					$param->name,
					($param->default ? '=' . $defaultValue : '')
				);
				
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
			'originalName'			=> $node->name,
			'weavedName'			=> static::WEAVED_METHOD_PREFIX . $node->name,
			'docComment'   			=> $node->getDocComment(),
			'signature'     		=> join($signature, ', '),
			'arguments'     		=> join($arguments, ', '),
			'interceptedArguments'	=> 'array(' . join($interceptedArguments, ',') .  ')',
		);
		
		$node->name = static::WEAVED_METHOD_PREFIX . $node->name;
		$node->setDocComment('');
	}

	private function insertInterceptorMethods(array &$subNodes, PHPParser_Node_Stmt_Class $node)
	{
		foreach ($this->methodsToWeave as $methodToWeave) {
			$code = '<?php
class ' . static::TEMP_CLASS_NAME . '
{
	public function ' . $methodToWeave['originalName'] . '(' . $methodToWeave['signature'] . ')
	{
		$' . static::TEMP_PARAMETER_PREFIX . 'interceptedArguments = ' . $methodToWeave['interceptedArguments'] . ';
	 
		$' . static::TEMP_PARAMETER_PREFIX . 'result = $this->' . $methodToWeave['weavedName'] . '(' . $methodToWeave['arguments'] . ');
		
		print "> intercepted: " . __METHOD__ . " with " . print_r($' . static::TEMP_PARAMETER_PREFIX . 'interceptedArguments, true);

		return $' . static::TEMP_PARAMETER_PREFIX . 'result;
	}
}
';
				
			//print $code;
			//print PHP_EOL;

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
				throw new RuntimeException('Error inserting methods', $e->getCode(), $e);	
			}
		}
	}

	private function fixSymfonyBundlePath(PHPParser_Node_Stmt_Class $node)
	{
		$code = '<?php
class ' . self::TEMP_CLASS_NAME . '
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

	public function leaveNode(PHPParser_Node $node) {
		if ($node instanceof PHPParser_Node_Stmt_ClassMethod && $node->type === PHPParser_Node_Stmt_Class::MODIFIER_PUBLIC) {
			$this->weavePublicClassMethod($node);
		}
		
		if (count($this->methodsToWeave) > 0 && $node instanceof PHPParser_Node_Stmt_Class) {
			$subNodes = $node->getIterator()->getArrayCopy();
			
			$this->insertInterceptorMethods($subNodes, $node);

			if (preg_match('#(.*)Bundle$#', $node->name)) {
				$this->fixSymfonyBundlePath($node);
			}

			if ($node instanceof PHPParser_Node_Stmt_Class) {
				$this->weavedFilePath = sys_get_temp_dir() . '/' . $node->namespacedName->toString('/') . '.php';
				$this->weavedNamespacedClassName = $node->namespacedName->toString();
			}
			
			return new PHPParser_Node_Stmt_Class($node->name, $subNodes, $node->getLine(), $node->getDocComment());
		}
	}
}