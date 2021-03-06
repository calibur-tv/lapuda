<?php

namespace App\Services\PingPP;

// e.g. metadata on Pingpp objects.
class AttachedObject extends PingppObject
{
    /**
     * Updates this object.
     *
     * @param array $properties A mapping of properties to update on this object.
     */
    public function replaceWith($properties)
    {
        $removed = array_diff(array_keys($this->_values), array_keys($properties));
        foreach ($removed as $k) {
            unset($this->$k);
        }

        foreach ($properties as $k => $v) {
            $this->$k = $v;
        }
    }
}
