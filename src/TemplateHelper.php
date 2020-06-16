<?php

namespace StaticHTMLOutput;

// phpcs:disable
class TemplateHelper {
    public function displayCheckbox(
        View $tpl_vars,
        string $field_name,
        string $field_label
    ) : void  {
        $options = $tpl_vars->__get('options');

        $checked = $options->{$field_name} === '1' ? 'checked' : '';

        echo "
      <fieldset>
        <label for='{$field_name}'>
          <input
            name='{$field_name}'
            id='{$field_name}'
            value='1'
            type='checkbox'
            $checked />
          <span>$field_label</span>
        </label>
      </fieldset>
    ";
    }

    public function displayTextfield(
        View $tpl_vars,
        string $field_name,
        string $field_label,
        string $description,
        string $type = 'text'
    ) : void  {
        $options = $tpl_vars->__get('options');

        echo "
      <input
        name='{$field_name}'
        class='regular-text'
        id='{$field_name}'
        type='{$type}'
        autocomplete='new-password'
        value='" . esc_attr( $options->{$field_name} ) . "' placeholder='" . $field_label . "' />
      <span class='description'>$description</span>
      <br>
    ";
    }

    /**
     * @param mixed[] $menu_options menu options
     */
    public function displaySelectMenu(
        View $tpl_vars,
        array $menu_options,
        string $field_name,
        string $field_label,
        string $description,
        string $type = 'text'
    ) : void {
        $menu_code = "
      <select name='{$field_name}' id='{$field_name}'>
        <option></option>";

        foreach ( $menu_options as $value => $text ) {
            $options = $tpl_vars->__get('options');

            if ( $options->{$field_name} === $value ) {
                $menu_code .= "
            <option value='{$value}' selected>{$text}</option>";
            } else {
                $menu_code .= "
            <option value='{$value}'>{$text}</option>";
            }
        }

        $menu_code .= '</select>';

        echo $menu_code;
    }

    /**
     * @param mixed $value template variable value
     * @return mixed template variable value
     */
    public function ifNotEmpty( $value, string $substitute = '' ) {
        $value = $value ? $value : $substitute;

        echo $value;
    }
}
// phpcs:enable

