<?php
/*
  Copyright 2005-2008 Redshift Software, Inc.
  Distributed under the Boost Software License, Version 1.0.
  (See accompanying file LICENSE_1_0.txt or http://www.boost.org/LICENSE_1_0.txt)
*/

class BoostFilterBoostBookHtml extends BoostFilters
{
    function echo_filtered() {
        $this->html_init();
        display_template($this->params,
            $this->template_params(
                $this->boost_book_html_filter_content()));
    }

    function boost_book_html_filter_content()
    {
        $text = $this->prepare_html($this->params['content'], true);

        $text = substr($text,strpos($text,'<div class="spirit-nav">'));
        $text = substr($text,0,strpos($text,'</body>'));
        $text = str_replace('<hr>','',$text);
        $text = str_replace('<table width="100%">','<table class="footer-table">',$text);
        $text = str_replace('<table xmlns:rev="http://www.cs.rpi.edu/~gregod/boost/tools/doc/revision" width="100%">','<table class="footer-table">',$text);
        $text = preg_replace(
            '@[\s]+(border|cellpadding|cellspacing|width|height|valign|frame|rules|naturalsizeflag|background)=[^\s>]+@i',
            '',
            $text );
        ##
        for ($i = 0; $i < 8; $i++) {
            $text = preg_replace(
                '@<img src="[\./a-z]*images/(prev|up|home|next|tip|note|warning|important|caution|sidebar|hint|alert)\.png" alt="([^"]+)"([ /]*)>@Ssm',
                '<img src="/gfx/space.png" alt="${2}" class="${1}_image" />',
                $text );
        }
        ##

        return $text;
    }
}
