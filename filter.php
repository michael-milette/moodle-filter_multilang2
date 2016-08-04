<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Multi-language content filter, with simplified syntax.
 *
 * @package    filter_multilang2
 * @copyright  Gaetan Frenoy <gaetan@frenoy.net>
 * @copyright  2004 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @copyright  2015 onwards Iñaki Arenaza & Mondragon Unibertsitatea
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 *  Given multilinguage text, return relevant text according to
 *  current language:
 *
 *    - look for multilang blocks in the text.
 *    - if there exists texts in the currently active language, print them.
 *    - else, if there exists texts in the current parent language, print them.
 *    - else, don't print any text inside the lang block (this is a change
 *      from previous filter versions behaviour!!!!)
 *
 *  Please note that English texts are not used as default anymore!
 *
 *  This version is based on original multilang filter by Gaetan Frenoy, Eloy and skodak.
 *
 *  Following new syntax is not compatible with old one:
 *    {mlang XX}one lang{mlang}Some common text for any language.{mlang YY}another language{mlang}
 *
 *  2016.08.04 A new enhanced syntax to be able to specify multiple languages
 *  for a single tag is now available. Just specify the list of the languages
 *  separated by commas:
 *    {mlang XX,YY,ZZ}Text displayed if current lang is XX, YY or ZZ, or one of their parent laguages.{mlang}
 *
 */
class filter_multilang2 extends moodle_text_filter {

    /**
     * This function filters the received text based on the language
     * tags embedded in the text, and the current user language.
     *
     * @param string $text The text to filter.
     * @param array $options The filter options.
     * @return string The filtered text for this multilang block.
     */
    public function filter($text, array $options = array()) {

        if (stripos($text, 'mlang') === false) {
            return $text;
        }

        $search = '/{\s*mlang\s+(                               # Look for the leading {mlang
                                    (?:[a-z0-9_-]+)             # At least one language must be present (but dont capture it individually)
                                    (?:\s*,\s*[a-z0-9_-]+\s*)*  # More can follow, separated by commas (again dont capture them individually)
                                )\s*}                           # Capture the language list as a single capture
                   (.*?)                                        # Now capture the text to be filtered
                   {\s*mlang\s*}                                # And look for the trailing {mlang}
                   /isx';
        $result = preg_replace_callback($search, 'filter_multilang2::replace_callback', $text);

        if (is_null($result)) {
            return $text; // Error during regex processing, keep original text.
        } else {
            return $result;
        }
    }

    /**
     * This function filters the current block of multilang tag. If any of the tag languages
     * (or their parent languages) matches the user current language, it returns the
     * text of the block. Otherwise it returns an empty string.
     *
     * @param array $langblock An array containing the matching captured pieces of the
     *                         regular expression. They are the languages of the tag,
     *                         and the text associated with those languages.
     * @return string
     */
    static protected function replace_callback($langblock) {
        static $parentcache;

        if (!isset($parentcache)) {
            $parentcache = array();
        }

        $mylang = current_language();
        if (!array_key_exists($mylang, $parentcache)) {
            $parentlangs = get_string_manager()->get_language_dependencies($mylang);
            $parentcache[$mylang] = $parentlangs;
        } else {
            $parentlangs = $parentcache[$mylang];
        }

        /* Normalize languages. We can use strtolower instead of core_text::strtolower()
         * as language short names are ASCII only, and strtolower is ~45% faster.
         */
        $langs = explode(',', str_replace(' ', '', str_replace('-', '_', strtolower($langblock[1]))));
        $text = $langblock[2];
        foreach($langs as $lang) {
            /* We don't check for empty values of $lang as they simply don't
             * match any language and they don't produce any errors or warnings.
             */
            if (($lang === $mylang) || in_array($lang, $parentlangs)) {
                return $text;
            }
        }
        return '';
    }
}
