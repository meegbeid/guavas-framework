<?php
/**
 * Form Validation
 *
 * PHP version 7.2
 */
class FormValidation
{

	protected array $_inputs = [];
	protected array $_errors = [];
	protected array $_roles = [];
	protected array $patterns = [
		'alias',
		'required',
		'int',
		'float',
		'string',
		'alphanum',
		'email',
		'length', 		// with value		
		'max',			// with value
		'min',			// with value
		'date'			// with format value
	];
	protected string $aliasName = '';

	public function __construct($formInput = [])
	{
		$this->_inputs = $formInput;
	}

	public function validate(array $roles = []) 
	{
		$this->_roles = $roles;
		$this->_errors = [];

		$this->runValidation();

		return empty($this->_errors);
	}

	public function hasError()
	{
		return !empty($this->_errors);
	}

	public function getErrors($asString = false)
	{
		return $asString ? implode(', ', $this->_errors) : $this->_errors; 
	}

	/**
     * Execute validation based on rules
     */
    protected function runValidation(): void
    {
        foreach ($this->_roles as $key => $role) {
            [$keyName, $aliasName] = array_pad(explode('|', $key), 2, '');
            $keyName = trim($keyName);
            $aliasName = trim($aliasName);

            $conditions = explode('|', $role);
            $this->aliasName = $aliasName !== '' ? $aliasName : $keyName;

            $this->checkRules($keyName, $conditions);
        }
    }

	protected function checkRules(string $key, array $conditions = [])
	{
		foreach ($conditions as $method) {
            [$methodName, $value] = array_pad(explode(':', $method), 2, '');

            if (in_array($methodName, $this->patterns, true)) {
                $callable = 'is_' . $methodName;
                if (method_exists($this, $callable)) {
                    $this->{$callable}($key, $value);
                }
            }
        }
	}

	protected function is_alias(string $key, string $value = ''): bool
	{
		// Kalau value alias dikirim, pakai itu
		if (!empty($value)) {
			$this->aliasName = $value;
		} else {
			$this->aliasName = $key;
		}

		return true;
	}

	protected function is_required($key)
	{
		$input = $this->_inputs;
		if(!isset($input[$key])) {
			$this->_errors[] = 'Field ' . $this->aliasName . ' is required';
			return false;
		}	
		return true;
	}

	protected function is_int($key)
	{
		$input = $this->_inputs;
		if(isset($input[$key]) && filter_var($input[$key], FILTER_VALIDATE_INT) === false) {
			$this->_errors[] = 'Field ' . $this->aliasName . ' must INT type';
		}
		return true;
	}

	protected function is_float($key)
	{
		$input = $this->_inputs;
		if(isset($input[$key]) && filter_var($input[$key], FILTER_VALIDATE_FLOAT) === false) {
			$this->_errors[] = 'Field ' . $this->aliasName . ' must FLOAT type';
		}
		return true;
	}

	protected function is_string($key)
	{
		$input = $this->_inputs;
		if(isset($input[$key]) && !is_string($input[$key])) {
			$this->_errors[] = 'Field ' . $this->aliasName . ' must STRING type';
		}
		return true;
	}

	/**
     * Validation for email format
     *
     * @param mixed $key
     * @return boolean
     */
    protected function is_email($key){
    	$input = $this->_inputs;
        if(filter_var($input[$key], FILTER_VALIDATE_EMAIL) === false) {
        	$this->_errors[] = 'Field ' . $this->aliasName . ' is invalid email';
        }
        return true;
    }

    protected function is_length($key, $value)
	{
		$input = $this->_inputs;
		if(isset($input[$key]) && (strlen($input[$key]) == (int)$value)) {
			$this->_errors[] = 'Field ' . $this->aliasName . ' length must '.$value.' characters';
		}
		return true;
	}

	protected function is_min($key, $value)
	{
		$input = $this->_inputs;
		if(isset($input[$key]) && (strlen($input[$key]) < (int)$value)) {
			$this->_errors[] = 'Field ' . $this->aliasName . ' min length is '.$value.' characters';
		}
		return true;
	}

	protected function is_max($key, $value)
	{
		$input = $this->_inputs;
		if(isset($input[$key]) && (strlen($input[$key]) > (int)$value)) {
			$this->_errors[] = 'Field ' . $this->aliasName . ' max length is '.$value.' characters';
		}
		return true;
	}
}