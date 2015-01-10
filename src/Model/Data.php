<?php

namespace Foolz\Foolslide\Model;


class Data {

    /**
     * @param array $data
     * @param string $prefix The prefix from the database, in example in "data.id", "data." is the prefix
     * @return static $this
     */
    public function import($data, $prefix = '') {
        foreach ($data as $key => $value) {

            // if the prefix is set and we found it, trim the prefix
            if ($prefix && mb_strpos($key, $prefix) === 0) {
                $key =  mb_substr($key, mb_strlen($prefix));
            } else if ($prefix) {
                // if the prefix is set but the prefix wasn't found, ignore the key
                continue;
            }

            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    public function export()
    {
        $result = [];
        foreach ($this as $key => $value) {
                $result[$key] = $value;
        }

        return $result;
    }

    public function getClone() {
        $new = new static();
        $new->import($this->export());
        return $new;
    }

}
