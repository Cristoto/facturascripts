<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2017-2018 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Core\Lib\Widget;

use FacturaScripts\Core\Model\CodeModel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Description of WidgetSelect
 *
 * @author Carlos García Gómez  <carlos@facturascripts.com>
 * @author Artex Trading sa     <jcuello@artextrading.com>
 */
class WidgetSelect extends BaseWidget
{

    /**
     *
     * @var CodeModel
     */
    protected static $codeModel;

    /**
     *
     * @var string
     */
    protected $fieldcode;

    /**
     *
     * @var string
     */
    protected $fieldtitle;

    /**
     *
     * @var string
     */
    protected $source;

    /**
     *
     * @var array
     */
    protected $values = [];

    /**
     *
     * @param array $data
     */
    public function __construct($data)
    {
        parent::__construct($data);
        if (!isset(static::$codeModel)) {
            static::$codeModel = new CodeModel();
        }

        foreach ($data['children'] as $child) {
            if ($child['tag'] !== 'values') {
                continue;
            }

            if (isset($child['source'])) {
                $this->setSourceData($child);
                break;
            } elseif (isset($child['title'])) {
                $this->setValuesFromArray($data['children'], isset($data['translate']), !$this->required, 'text');
                break;
            } elseif (isset($child['start'])) {
                $this->setValuesFromRange($child['start'], $child['end'], $child['step']);
                break;
            }
        }
    }

    /**
     * Obtains the configuration of the datasource used in obtaining data
     *
     * @return array
     */
    public function getDataSource(): array
    {
        return [
            'source' => $this->source,
            'fieldcode' => $this->fieldcode,
            'fieldtitle' => $this->fieldtitle
        ];
    }

    /**
     *
     * @param object  $model
     * @param Request $request
     */
    public function processFormData(&$model, $request)
    {
        $value = $request->request->get($this->fieldname);
        $model->{$this->fieldname} = empty($value) ? null : $value;
    }

    /**
     * Loads the value list from a given array.
     * The array must have one of the two following structures:
     * - If it's a value array, it must uses the value of each element as title and value
     * - If it's a multidimensional array, the indexes value and title must be set for each element
     *
     * @param array  $values
     * @param bool   $translate
     * @param bool   $addEmpty
     * @param string $col1
     * @param string $col2
     */
    public function setValuesFromArray($values, $translate = false, $addEmpty = false, $col1 = 'value', $col2 = 'title')
    {
        $this->values = [];
        if ($addEmpty) {
            $this->values[] = ['value' => null, 'title' => '------'];
        }

        foreach ($values as $value) {
            if (is_array($value)) {
                $this->values[] = [
                    'title' => isset($value[$col2]) ? $value[$col2] : '------',
                    'value' => isset($value[$col1]) ? $value[$col1] : null,
                ];
                continue;
            }

            $this->values[] = [
                'value' => $value,
                'title' => '',
            ];
        }

        if ($translate) {
            $this->applyTranslations();
        }
    }

    /**
     * Loads the value list from an array with value and title (description)
     *
     * @param array $rows
     * @param bool  $translate
     */
    public function setValuesFromCodeModel(&$rows, $translate = false)
    {
        $this->values = [];
        foreach ($rows as $codeModel) {
            $this->values[] = [
                'value' => $codeModel->code,
                'title' => $codeModel->description,
            ];
        }

        if ($translate) {
            $this->applyTranslations();
        }
    }

    /**
     *
     * @param int $start
     * @param int $end
     * @param int $step
     */
    public function setValuesFromRange($start, $end, $step)
    {
        $values = range($start, $end, $step);
        $this->setValuesFromArray($values);
    }

    /**
     *  Translate the fixed titles, if they exist
     */
    private function applyTranslations()
    {
        foreach ($this->values as $key => $value) {
            if (!empty($value['title'])) {
                $this->values[$key]['title'] = static::$i18n->trans($value['title']);
            }
        }
    }

    /**
     *
     * @param string $type
     * @param string $extraClass
     *
     * @return string
     */
    protected function inputHtml($type = 'text', $extraClass = '')
    {
        $html = '<select name="' . $this->fieldname . '" class="form-control"' . $this->inputHtmlExtraParams() . '>';
        foreach ($this->values as $option) {
            /// don't use strict comparation (===)
            $selected = ($option['value'] == $this->value) ? ' selected="selected" ' : '';
            $title = empty($option['title']) ? $option['value'] : $option['title'];
            $html .= '<option value="' . $option['value'] . '" ' . $selected . '>' . $title . '</option>';
        }

        $html .= '</select>';
        return $html;
    }

    /**
     * Set datasource data and Load data from Model into values array.
     *
     * @param array $child
     * @param bool  $loadData
     */
    protected function setSourceData(array $child, bool $loadData = true)
    {
        $this->source = $child['source'];
        $this->fieldcode = $child['fieldcode'];
        $this->fieldtitle = $child['fieldtitle'] ?? $this->fieldcode;
        if ($loadData) {
            $values = static::$codeModel->all($this->source, $this->fieldcode, $this->fieldtitle, !$this->required);
            $this->setValuesFromCodeModel($values, isset($child['translate']));
        }
    }

    /**
     *
     * @return string
     */
    protected function show()
    {
        if (is_null($this->value)) {
            return '-';
        }

        $selected = null;
        foreach ($this->values as $option) {
            /// don't use strict comparation (===)
            if ($option['value'] == $this->value) {
                $selected = $option['title'];
            }
        }

        if (null === $selected) {
            // value is not in $this->values
            $selected = static::$codeModel->getDescription($this->source, $this->fieldcode, $this->value, $this->fieldtitle);
            $this->values[] = [
                'value' => $this->value,
                'title' => $selected,
            ];
        }

        return $selected;
    }
}
