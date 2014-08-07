<?php
/*
  Copyright 2005-2008 Redshift Software, Inc.
  Distributed under the Boost Software License, Version 1.0.
  (See accompanying file LICENSE_1_0.txt or http://www.boost.org/LICENSE_1_0.txt)
*/

class BoostFilterCpp extends BoostFilterText
{
    function echo_filtered() {
        $this->params['title'] = html_encode($this->params['key']);

        display_template($this->params,
            $this->template_params($this->cpp_filter_content()));
    }

    function cpp_filter_content()
    {
        return
            "<h3>".html_encode($this->params['key'])."</h3>\n".
            "<pre>\n".
            $this->encoded_text('cpp').
            "</pre>\n";
    }
}
