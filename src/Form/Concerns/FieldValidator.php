<?php

namespace Dcat\Admin\Form\Concerns;

use Dcat\Admin\Form;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * @property Form $form
 */
trait FieldValidator
{
    /**
     * The validation rules for creation.
     *
     * @var array|\Closure
     */
    protected $creationRules = [];

    /**
     * The validation rules for updates.
     *
     * @var array|\Closure
     */
    protected $updateRules = [];

    /**
     * Validation rules.
     *
     * @var string|\Closure
     */
    protected $rules = '';

    /**
     * @var \Closure
     */
    protected $validator;

    /**
     * Validation messages.
     *
     * @var array
     */
    protected $validationMessages = [];

    /**
     * Set the update validation rules for the field.
     *
     * @param array|callable|string $rules
     * @param array $messages
     *
     * @return $this
     */
    public function updateRules($rules = null, $messages = [])
    {
        $this->updateRules = $this->mergeRules($rules, $this->updateRules);

        $this->setValidationMessages('update', $messages);

        return $this;
    }

    /**
     * Set the creation validation rules for the field.
     *
     * @param array|callable|string $rules
     * @param array $messages
     *
     * @return $this
     */
    public function creationRules($rules = null, $messages = [])
    {
        $this->creationRules = $this->mergeRules($rules, $this->creationRules);

        $this->setValidationMessages('creation', $messages);

        return $this;
    }

    /**
     * Get or set rules.
     *
     * @param null $rules
     * @param array $messages
     *
     * @return $this
     */
    public function rules($rules = null, $messages = [])
    {
        if ($rules instanceof \Closure) {
            $this->rules = $rules;
        }

        if (is_array($rules)) {
            $thisRuleArr = array_filter(explode('|', $this->rules));

            $this->rules = array_merge($thisRuleArr, $rules);
        } elseif (is_string($rules)) {
            $rules = array_filter(explode('|', "{$this->rules}|$rules"));

            $this->rules = implode('|', $rules);
        }

        $this->setValidationMessages('default', $messages);

        return $this;
    }

    /**
     * Get field validation rules.
     *
     * @return string
     */
    protected function getRules()
    {
        if (request()->isMethod('POST')) {
            $rules = $this->creationRules ?: $this->rules;
        } elseif (request()->isMethod('PUT')) {
            $rules = $this->updateRules ?: $this->rules;
        } else {
            $rules = $this->rules;
        }

        if ($rules instanceof \Closure) {
            $rules = $rules->call($this, $this->form);
        }

        if (is_string($rules)) {
            $rules = array_filter(explode('|', $rules));
        }

        if (!$this->form) {
            return $rules;
        }

        if (!$id = $this->form->getKey()) {
            return $rules;
        }

        if (is_array($rules)) {
            foreach ($rules as &$rule) {
                if (is_string($rule)) {
                    $rule = str_replace('{{id}}', $id, $rule);
                }
            }
        }

        return $rules;
    }

    /**
     * Format validation rules.
     *
     * @param array|string $rules
     *
     * @return array
     */
    protected function formatRules($rules)
    {
        if (is_string($rules)) {
            $rules = array_filter(explode('|', $rules));
        }

        return array_filter((array) $rules);
    }


    /**
     * @param string|array|\Closure $input
     * @param string|array         $original
     *
     * @return array|\Closure
     */
    protected function mergeRules($input, $original)
    {
        if ($input instanceof \Closure) {
            $rules = $input;

        } else {
            if (!empty($original)) {
                $original = $this->formatRules($original);
            }
            $rules = array_merge($original, $this->formatRules($input));
        }

        return $rules;
    }

    /**
     * Remove a specific rule by keyword.
     *
     * @param string $rule
     *
     * @return void
     */
    public function removeRule($rule)
    {
        if (!$this->rules || !is_string($this->rules)) {
            return;
        }

        $pattern = "/{$rule}[^\|]?(\||$)/";
        $this->rules = preg_replace($pattern, '', $this->rules, -1);
    }

    /**
     * @param string $rule
     *
     * @return bool
     */
    public function hasRule($rule)
    {
        if (!$this->rules || !is_string($this->rules)) {
            return false;
        }

        $pattern = "/{$rule}[^\|]?(\||$)/";

        return preg_match($pattern, $this->rules);
    }

    /**
     * Set field validator.
     *
     * @param callable $validator
     *
     * @return $this
     */
    public function validator(callable $validator)
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Get validator for this field.
     *
     * @param array $input
     *
     * @return bool|Validator
     */
    public function getValidator(array $input)
    {
        if ($this->validator) {
            return $this->validator->call($this, $input);
        }

        $rules = $attributes = [];

        if (!$fieldRules = $this->getRules()) {
            return false;
        }

        if (is_string($this->column)) {
            if (!Arr::has($input, $this->column)) {
                return false;
            }

            $input = $this->sanitizeInput($input, $this->column);

            $rules[$this->column] = $fieldRules;
            $attributes[$this->column] = $this->label;
        }

        if (is_array($this->column)) {
            foreach ($this->column as $key => $column) {
                if (!array_key_exists($column, $input)) {
                    continue;
                }
                $input[$column . $key] = Arr::get($input, $column);
                $rules[$column . $key] = $fieldRules;
                $attributes[$column . $key] = $this->label . "[$column]";
            }
        }

        return Validator::make($input, $rules, $this->getValidationMessages(), $attributes);
    }


    /**
     * Set validation messages for column.
     *
     * @param string $key
     * @param array $messages
     *
     * @return $this
     */
    public function setValidationMessages($key, array $messages)
    {
        $this->validationMessages[$key] = $messages;

        return $this;
    }

    /**
     * Get validation messages for the field.
     *
     * @return array|mixed
     */
    public function getValidationMessages()
    {
        // Default validation message.
        $messages = $this->validationMessages['default'] ?? [];

        if (request()->isMethod('POST')) {
            $messages = $this->validationMessages['creation'] ?? $messages;
        } elseif (request()->isMethod('PUT')) {
            $messages = $this->validationMessages['update'] ?? $messages;
        }

        return $messages;
    }


}