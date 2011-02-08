<?php

namespace Respect\Validation\Exceptions;

use DateTime;
use Exception;
use InvalidArgumentException;
use RecursiveIteratorIterator;
use RecursiveTreeIterator;
use Respect\Validation\ExceptionIterator;
use Respect\Validation\Reportable;

class ValidationException extends InvalidArgumentException
{
    const STANDARD = 0;
    const ITERATE_TREE = 1;
    const ITERATE_ALL = 2;
    public static $defaultTemplates = array(
        self::STANDARD => 'Data validation failed for %s'
    );
    protected $context = null;
    protected $id = '';
    protected $params = array();
    protected $related = array();
    protected $template = '';

    public static function create($name=null)
    {
        $i = new static;
        if (func_get_args() > 0)
            return call_user_func_array(array($i, 'configure'), func_get_args());
        else
            return $i;
    }

    public static function stringify($value)
    {
        if (is_string($value))
            return $value;
        elseif (is_object($value))
            if (method_exists($value, '__toString'))
                return (string) $value;
            elseif ($value instanceof DateTime)
                return $value->format('Y-m-d H:i:s');
            else
                return "Object of class " . get_class($value);
        else
            return (string) $value;
    }

    public function __toString()
    {
        return $this->getMainMessage();
    }

    public function addRelated(ValidationException $related)
    {
        $this->related[] = $related;
        return $this;
    }

    public function chooseTemplate()
    {
        return key(static::$defaultTemplates);
    }

    public function configure($name)
    {
        $this->params = func_get_args();
        foreach ($this->params as &$p) 
            $p = static::stringify($p);
        $this->message = $this->getMainMessage();
        $this->guessId();
        return $this;
    }

    public function findRelated()
    {
        $target = $this;
        $path = func_get_args();
        while (!empty($path) && $target !== false)
            $target = $this->getRelatedByName(array_shift($path));
        return $target;
    }

    public function getFullMessage()
    {
        $message = array();
        foreach ($this->getIterator(false, self::ITERATE_TREE) as $m)
            $message[] = $m;
        return implode(PHP_EOL, $message);
    }

    public function getName()
    {
        return $this->params[0];
    }

    public function getId()
    {
        return $this->id;
    }

    public function getIterator($full=false, $mode=self::ITERATE_ALL)
    {
        $exceptionIterator = new ExceptionIterator($this, $full);
        if ($mode == self::ITERATE_ALL)
            return new RecursiveIteratorIterator($exceptionIterator, 1);
        else
            return new RecursiveTreeIterator($exceptionIterator);
    }

    public function getMainMessage()
    {
        $sprintfParams = $this->getParams();
        if (empty($sprintfParams))
            return $this->message;
        array_unshift($sprintfParams, $this->getTemplate());
        return call_user_func_array('sprintf', $sprintfParams);
    }

    public function getParams()
    {
        $params = array_slice($this->params, 1);
        array_unshift($params, $this->getName());
        return $params;
        
    }

    public function getRelated()
    {
        return $this->related;
    }

    public function getRelatedByName($name)
    {
        foreach ($this->getIterator(true) as $e)
            if ($e->getId() === $name)
                return $e;
        return false;
    }

    public function getTemplate()
    {
        if (!empty($this->template))
            return $this->template;
        $templateKey = call_user_func_array(
            array($this, 'chooseTemplate'), $this->getParams()
        );
        if (is_null($this->context))
            $this->template = static::$defaultTemplates[$templateKey];
        else
            $this->template = $this->context->getTemplate($this, $templateKey);
        return $this->template;
    }

    public function setContext($context)
    {
        $this->context = $context;
        foreach ($this->related as $r)
            $r->setContext($context);
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function setName($name)
    {
        $this->params[0] = static::stringify($name);
        return $this;
    }

    public function setRelated(array $relatedExceptions)
    {
        foreach ($relatedExceptions as $related)
            $this->addRelated($related);
        return $this;
    }

    public function setTemplate($template)
    {
        $this->template = $template;
    }

    protected function guessId()
    {
        if (!empty($this->id))
            return;
        $id = end(explode('\\', get_called_class()));
        $id = lcfirst(str_replace('Exception', '', $id));
        $this->setId($id);
    }

}