<?php
/*
  Copyright 2006 Redshift Software, Inc.
  Distributed under the Boost Software License, Version 1.0.
  (See accompanying file LICENSE_1_0.txt or http://www.boost.org/LICENSE_1_0.txt)
*/

require_once(dirname(__FILE__) . '/boost_utility.php');
require_once(dirname(__FILE__) . '/boost_version.php');

class boost_libraries
{
    private $categories = array();
    private $db = array();

    /**
     * Read library details from an xml file.
     *
     * @param string $file_path
     * @return \boost_libraries
     */
    static function from_file($file_path)
    {
        return new boost_libraries(file_get_contents($file_path));
    }

    /**
     * Read library details from an xml string
     *
     * @param string $xml
     * @return \boost_libraries
     */
    static function from_string($xml)
    {
        return new boost_libraries($xml);
    }

    /**
     * Create from an array of library details.
     *
     * This is pretty hacky, I only added for a one-off function. If you want
     * to make use of it, it could probably do with some sanity checks to
     * make sure that the libraries are valid.
     *
     * @param array $libraries
     * @return \boost_libraries
     */
    static function from_array($libraries)
    {
        $x = new boost_libraries('<boost></boost>');

        foreach ($libraries as $lib) {
            $version = isset($lib['update-version']) ? $lib['update-version'] :
                $lib['boost-version'];
            $x->db[$lib['key']][(string) $version] = $lib;
        }

        return $x;
    }

    private function __construct($xml)
    {
        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        if (!xml_parse_into_struct($parser, $xml, $values)) {
            die("Error parsing XML");
        }
        xml_parser_free($parser);

        ##print '<!-- '; print_r($values); print ' -->';
        
        $lib = NULL;
        $category = NULL;
        foreach ( $values as $key => $val )
        {
            if ($val['tag'] == 'boost' || $val['tag'] == 'categories')
            {
                // Ignore boost tags.
            }
            else if ($val['tag'] == 'category' && $val['type'] == 'open' && !$lib && !$category)
            {
                $category = isset($val['attributes']) ? $val['attributes'] : array();
            }
            else if($val['tag'] == 'title' && $category)
            {
                $category['title'] = isset($val['value']) ? trim($val['value']) : '';
            }
            else if ($val['tag'] == 'category' && $val['type'] == 'close' && $category)
            {
                $this->categories[$category['name']] = $category;
                $category = NULL;
            }
            else if ($val['tag'] == 'library' && $val['type'] == 'open')
            {
                $lib = array();
            }
            else if ($val['type'] == 'complete')
            {
                switch ($val['tag'])
                {
                    case 'key':
                    case 'name':
                    case 'authors':
                    case 'description':
                    case 'documentation':
                    case 'status':
                    case 'module':
                    {
                        if (isset($val['value'])) { $lib[$val['tag']] = trim($val['value']); }
                        else { $lib[$val['tag']] = ''; }
                    }
                    break;
                    case 'boost-version':
                    case 'update-version':
                    {
                        if (isset($val['value'])) { $lib[$val['tag']] = BoostVersion::from($val['value']); }
                        else { $lib[$val['tag']] = ''; }
                    }
                    break;
                    case 'std-proposal':
                    case 'std-tr1':
                    {
                        $value = isset($val['value']) ? trim($val['value']) : false;
                        if($value && $value != 'true' && $value != 'false') {
                            echo 'Invalid value for ',htmlentities($val['tag']),
                                ': ', $value, "\n";
                            exit(0);
                        }
                        $lib[$val['tag']] = ($value == 'true');
                    }
                    break;
                    case 'category':
                    {
                        if(isset($val['value'])) {
                            $name = trim($val['value']);
                            $lib['category'][] = $name;
                        }
                    }
                    break;
                    default:
                        echo 'Invalid tag: ', htmlentities($val['tag']), "\n";
                        exit(0);
                }
            }
            else if ($val['tag'] == 'library' && $val['type'] == 'close' && $lib)
            {
                assert(isset($lib['boost-version']));
                assert(isset($lib['key']));

                if (!isset($lib['update-version'])) {
                    $lib['update-version'] = $lib['boost-version'];
                }

                if (!isset($lib['module'])) {
                    $key_parts = explode('/', $lib['key'], 2);
                    $lib['module'] = $key_parts[0];
                }

                $this->db[$lib['key']][(string) $lib['update-version']] = $lib;
                $lib = NULL;
            }
            else
            {
                echo 'Invalid tag: ', htmlentities($val['tag']), "\n";
                exit(0);
            }
        }

        foreach (array_keys($this->db) as $key) {
            $this->sort_versions($key);

            foreach (array_keys($this->db[$key]) as $version) {
                sort($this->db[$key][$version]['category']);
            }
        }
    }

    public function update($xml, $update_version) {
        $update_version = BoostVersion::from($update_version);
        $version_key = (string) $update_version;
        $update = new boost_libraries($xml);

        foreach($update->db as $key => $libs) {
            if (count($libs) > 1) {
                throw new boost_libraries_exception("Duplicate key: {$key}\n");
            }

            $details = reset($libs);
            $details['update-version'] = $update_version;

            $this->db[$key][$version_key] = $details;
            $this->reduce_versions($key);
        }
    }

    private function sort_versions($key) {
        uasort($this->db[$key], function($x, $y) {
            return $x['update-version']->compare($y['update-version']);
        });
    }

    private function reduce_versions($key) {
        $this->sort_versions($key);
        $last = null;

        foreach ($this->db[$key] as $version => $current) {
            if ($last) {
                if ($this->equal_details($last, $current)) {
                    unset($this->db[$key][$version]);
                }
            }

            $last = $current;
        }
    }

    private function equal_details($details1, $details2) {
        if (count(array_diff_key($details1, $details2))) {
            return false;
        }

        foreach($details1 as $key => $value) {
            if ($key == 'update-version') continue;

            if (is_object($value)) {
                if ($value->compare($details2[$key]) != 0) return false;
            }
            else {
                if ($value != $details2[$key]) return false;
            }
        }

        return true;
    }
    
    /**
     * Generate an xml representation of the library data.
     *
     * @param array $exclude Fields to leave out of the library output
     * @return string
     */
    function to_xml($exclude = array()) {
        $exclude = array_flip($exclude);

        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');

        $writer->startDocument('1.0', 'US-ASCII');
        $writer->startElement('boost');
        $writer->writeAttribute('xmlns:xsi',
                'http://www.w3.org/2001/XMLSchema-instance');

        if ($this->categories) {
            $writer->startElement('categories');
            foreach ($this->categories as $name => $category) {
                $writer->startElement('category');
                $writer->writeAttribute('name', $name);
                $writer->writeElement('title', $category['title']);
                $writer->endElement();
            }
            $writer->endElement(); // categories
        }

        foreach ($this->db as $key => $libs) {
            foreach($libs as $lib) {
                $writer->startElement('library');
                $this->write_element($writer, $exclude, $lib, 'key');
                $this->write_element($writer, $exclude, $lib, 'module');
                $this->write_element($writer, $exclude, $lib, 'boost-version');
                if ($lib['update-version'] != $lib['boost-version']) {
                    $this->write_element($writer, $exclude, $lib, 'update-version');
                }
                $this->write_optional_element($writer, $exclude, $lib, 'status');
                $this->write_optional_element($writer, $exclude, $lib, 'name');
                $this->write_optional_element($writer, $exclude, $lib, 'authors');
                $this->write_optional_element($writer, $exclude, $lib, 'description');
                $this->write_optional_element($writer, $exclude, $lib, 'documentation');
                $this->write_optional_element($writer, $exclude, $lib, 'std-proposal');
                $this->write_optional_element($writer, $exclude, $lib, 'std-tr1');
                if (!isset($exclude['category'])) {
                    foreach($lib['category'] as $category) {
                        $writer->writeElement('category', $category);
                    }
                }
                $writer->endElement();
            }
        }

        $writer->endElement(); // boost
        $writer->endDocument();
        return $writer->outputMemory();
    }

    /**
     * Write a library element.
     *
     * @param XMLWriter $writer
     * @param array $exclude
     * @param string $lib
     * @param string $name
     */
    private function write_element($writer, $exclude, $lib, $name) {
        if (!isset($exclude[$name])) {
            $value = $lib[$name];
            $value = is_bool($value) ?
                    ($value ? "true" : "false") :
                    (string) $value;

            $writer->writeElement($name, $value);
        }
    }

    /**
     * Write a library element.
     *
     * @param XMLWriter $writer
     * @param array $exclude
     * @param string $lib
     * @param string $name
     */
    private function write_optional_element($writer, $exclude, $lib, $name) {
        if (isset($lib[$name])) {
            $this->write_element($writer, $exclude, $lib, $name);
        }
    }

    /**
     * Get the library details for a particular release.
     *
     * @param \BoostVersion $version
     * @param string $sort Optional field used to sort the libraries.
     * @param callable $filter Optional filter function.
     * @return array
     */
    function get_for_version($version, $sort = null, $filter = null) {
        $libs = array();

        foreach($this->db as $key => $versions) {
            $lib = null;

            foreach($versions as $l) {
                if ($version->compare($l['update-version']) >= 0) {
                    $lib = $l;
                }
            }

            if ($lib) {
                if ($filter && !call_user_func($filter, $lib)) continue;

                $libs[$key] = $lib;
            }
        }

        $libs = array_values($libs);
        if($sort) {
            uasort($libs, sort_by_field($sort));
        }
        return $libs;
    }

    /**
     * Get the library details for a particular release, grouped into
     * categories.
     *
     * @param \BoostVersion $version
     * @param string $sort Optional field used to sort the libraries.
     * @param callable $filter Optional filter function.
     * @return array
     */
    function get_categorized_for_version($version, $sort = null, $filter = null) {
        $libs = $this->get_for_version($version, $sort, $filter);
        $categories = $this->categories;

        foreach($libs as $key => $library) {
            foreach($library['category'] as $category) {
                if(!isset($this->categories[$category])) {
                    echo 'Unknown category: ', htmlentities($category), "\n";
                    exit(0);
                }
                $categories[$category]['libraries'][] = $library;
            }
        }

        return $categories;
    }

    /**
     * Get a list of the different categories.
     *
     * @return string
     */
    function get_categories() {
        return $this->categories;
    }

    /**
     * Get the full history of a library.
     *
     * @param string $key
     * @return array
     */
    function get_history($key) {
        return $this->db[$key];
    }
}

class boost_libraries_exception extends RuntimeException {}

?>
