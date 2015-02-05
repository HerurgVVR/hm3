<?php

function message_list_meta($input, $output_mod) {
    if (!array_key_exists('list_meta', $input) || !$input['list_meta']) {
        return '';
    }
    $limit = 0;
    $since = false;
    $times = array(
        'today' => 'Today',
        '-1 week' => 'Last 7 days',
        '-2 weeks' => 'Last 2 weeks',
        '-4 weeks' => 'Last 4 weeks',
        '-6 weeks' => 'Last 6 weeks',
        '-6 months' => 'Last 6 months',
        '-1 year' => 'Last year'
    );
    if (array_key_exists('per_source_limit', $input)) {
        $limit = $input['per_source_limit'];
    }
    if (!$limit) {
        $limit = DEFAULT_PER_SOURCE;
    }
    if (array_key_exists('message_list_since', $input)) {
        $since = $input['message_list_since'];
    }
    if (!$since) {
        $since = DEFAULT_SINCE;
    }
    $dt = sprintf('%s', strtolower($output_mod->trans($times[$since])));
    $max = sprintf($output_mod->trans('sources@%d each'), $limit);

    return '<div class="list_meta">'.
        $output_mod->html_safe($dt).
        '<b>-</b>'.
        '<span class="src_count"></span> '.$max.
        '<b>-</b>'.
        '<span class="total"></span> '.$output_mod->trans('total').'</div>';
}

function human_readable_interval($date_str) {
    $precision     = 2;
    $interval_time = array();
    $now           = time();
    $date          = strtotime($date_str);
    $interval      = $now - $date;
    $res           = array();

    $t['second'] = 1;
    $t['minute'] = $t['second']*60;
    $t['hour']   = $t['minute']*60;
    $t['day']    = $t['hour']*24;
    $t['week']   = $t['day']*7;
    $t['month']  = $t['day']*30;
    $t['year']   = $t['week']*52;

    if ($interval < 0) {
        $interval += $t['hour'];
        if ($interval < 0) {
            return 'From the future!';
        }
    }
    elseif ($interval == 0) {
        return 'Just now';
    }

    foreach (array_reverse($t) as $name => $val) {
        if ($interval_time[$name] = ($interval/$val > 0) ? floor($interval/$val) : false) {
            $interval -= $val*$interval_time[$name];
        }
    }

    $interval_time = array_slice(array_filter($interval_time, function($v) { return $v > 0; }), 0, $precision);

    foreach($interval_time as $name => $val) {
        if ($val > 1) {
            $res[] = sprintf('%d %ss', $val, $name);
        }
        else {
            $res[] = sprintf('%d %s', $val, $name);
        }
    }
    return implode(', ', $res);
}

function message_list_row($values, $id, $style, $output_mod) {
    $res = '<tr style="display: none;" class="'.$output_mod->html_safe(str_replace(' ', '-', $id)).'">';
    if ($style == 'news') {
        $res .= '<td class="news_cell checkbox_cell">';
    }
    foreach ($values as $vals) {
        if (function_exists($vals[0])) {
            $function = array_shift($vals);
            $res .= $function($vals, $style, $output_mod);
        }
    }
    if ($style == 'news') {
        $res .= '</td>';
    }
    $res .= '</tr>';
    return array($res, $id);
}

function safe_output_callback($vals, $style, $output_mod) {
    if ($style == 'email') {
        return sprintf('<td class="%s" title="%s">%s</td>', $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]), $output_mod->html_safe($vals[1]));
    }
    elseif ($style == 'news') {
        return sprintf('<div class="%s" title="%s">%s</div>', $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]), $output_mod->html_safe($vals[1]));
    }
}
function checkbox_callback($vals, $style, $output_mod) {
    if ($style == 'email') {
        return sprintf('<td class="checkbox_cell"><input type="checkbox" value="%s" /></td>', $output_mod->html_safe($vals[0]));
    }
    elseif ($style == 'news') {
        return sprintf('<input type="checkbox" value="%s" /></td><td class="news_cell">', $output_mod->html_safe($vals[0]));
    }
}
function subject_callback($vals, $style, $output_mod) {
    if ($style == 'email') {
        return sprintf('<td class="subject"><div class="%s"><a title="%s" href="%s">%s</a></div></td>', $output_mod->html_safe(implode(' ', $vals[2])), $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]), $output_mod->html_safe($vals[0]));
    }
    elseif ($style == 'news') {
        return sprintf('<div class="subject"><div class="%s" title="%s"><a href="%s">%s</a></div></div>', $output_mod->html_safe(implode(' ', $vals[2])), $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]), $output_mod->html_safe($vals[0]));
    }
}
function date_callback($vals, $style, $output_mod) {
    if ($style == 'email') {
        return sprintf('<td class="msg_date" title="%s">%s<input type="hidden" class="msg_timestamp" value="%s" /></td>', $output_mod->html_safe(date('r', $vals[1])), $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]));
    }
    elseif ($style == 'news') {
        return sprintf('<div class="msg_date">%s<input type="hidden" class="msg_timestamp" value="%s" /></div>', $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]));
    }
}
function icon_callback($vals, $style, $output_mod) {
    if ($style == 'email') {
        return sprintf('<td class="icon">%s</td>', (in_array('flagged', $vals[0]) ? '&#9733;' : ''));
    }
    elseif ($style == 'news') {
        return sprintf('<div class="icon">%s</div>', (in_array('flagged', $vals[0]) ? '&#9733;' : ''));
    }
}

function message_controls($output_mod) {
    return '<a class="toggle_link" href="#"><img alt="x" src="'.Hm_Image_Sources::$check.'" width="8" height="8" /></a>'.
        '<div class="msg_controls">'.
        '<a href="#" data-action="read">'.$output_mod->trans('Read').'</a>'.
        '<a href="#" data-action="unread">'.$output_mod->trans('Unread').'</a>'.
        '<a href="#" data-action="flag">'.$output_mod->trans('Flag').'</a>'.
        '<a href="#" data-action="unflag">'.$output_mod->trans('Unflag').'</a>'.
        '<a href="#" data-action="delete">'.$output_mod->trans('Delete').'</a></div>';
}

function message_since_dropdown($since, $name, $output_mod) {
    $times = array(
        'today' => 'Today',
        '-1 week' => 'Last 7 days',
        '-2 weeks' => 'Last 2 weeks',
        '-4 weeks' => 'Last 4 weeks',
        '-6 weeks' => 'Last 6 weeks',
        '-6 months' => 'Last 6 months',
        '-1 year' => 'Last year'
    );
    $res = '<select name="'.$name.'" id="'.$name.'" class="message_list_since">';
    foreach ($times as $val => $label) {
        $res .= '<option';
        if ($val == $since) {
            $res .= ' selected="selected"';
        }
        $res .= ' value="'.$val.'">'.$output_mod->trans($label).'</option>';
    }
    $res .= '</select>';
    return $res;
}

function process_since_argument($val, $validate=false) {
    $date = false;
    $valid = false;
    if (in_array($val, array('-1 week', '-2 weeks', '-4 weeks', '-6 weeks', '-6 months', '-1 year'))) {
        $valid = $val;
        $date = date('j-M-Y', strtotime($val));
    }
    else {
        $val = 'today';
        $valid = $val;
        $date = date('j-M-Y');
    }
    if ($validate) {
        return $valid;
    }
    return $date;
}

function format_msg_html($str, $external_resources=false) {
    require APP_PATH.'third_party/HTMLPurifier.standalone.php';
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    if (!$external_resources) {
        $config->set('URI.AllowedSchemes', array('data' => true));
    }
    $config->set('HTML.TargetBlank', true);
    $config->set('Filter.ExtractStyleBlocks.TidyImpl', true);
    $purifier = new HTMLPurifier($config);
    $res = $purifier->purify($str);
    return $res;
}

function format_msg_image($str, $mime_type) {
    return '<img alt="" src="data:image/'.$mime_type.';base64,'.chunk_split(base64_encode($str)).'" />';
}

function format_msg_text($str, $output_mod, $links=true) {
    $str = str_replace("\t", '    ', $str);
    $str = nl2br(str_replace(' ', '<wbr>', ($output_mod->html_safe($str))));
    if ($links) {
        $link_regex = "/((http|ftp|rtsp)s?:\/\/(%[[:digit:]A-Fa-f][[:digit:]A-Fa-f]|[-_\.!~\*';\/\?#:@&=\+$,\[\]%[:alnum:]])+)/m";
        $str = preg_replace($link_regex, "<a target=\"_blank\" href=\"$1\">$1</a>", $str);
    }
    return str_replace('<wbr>', '&#160;<wbr>', $str);
}

function display_value($name, $haystack, $type=false, $default='') {
    if (!array_key_exists($name, $haystack)) {
        return $default;
    }
    $value = $haystack[$name];
    $res = false;
    if ($type) {
        $name = $type;
    }
    switch($name) {
        case 'from':
            $value = preg_replace("/(\<.+\>)/U", '', $value);
            $res = str_replace('"', '', $value);
            break;
        case 'date':
            $res = human_readable_interval($value);
            break;
        case 'time':
            $res = strtotime($value);
            break;
        default:
            $res = $value;
            break;
    }
    return $res;
}

function format_reply_text($txt) {
    return '> '.str_replace("\n", "\n> ", $txt);
}

function interface_langs() {
    return array(
        'en' => 'English',
        'es' => 'Spanish',
        'zh' => 'Chinese (Simplified)',
        'zh_TW' => 'Chinese (Traditional)',
        'ar' => 'Arabic',
        'fr' => 'French',
        'nl' => 'Dutch',
        'de' => 'German',
        'hi' => 'Hindi',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'ru' => 'Russian',
        'ro' => 'Romanian',
        'sv' => 'Sweedish',
        'th' => 'Thai',
        'vi' => 'Vietnamese',
        'cs' => 'Czech',
        'da' => 'Danish',
        'et' => 'Estonian',
        'fi' => 'Finish',
        'tl' => 'Filipino',
        'ka' => 'Georgian',
        'el' => 'Greek',
        'iw' => 'Hebrew',
        'hu' => 'Hungarian',
        'id' => 'Indonesian',
        'kn' => 'Kannada',
        'lo' => 'Lao',
        'lv' => 'Latvian',
        'lt' => 'Lithuanian',
        'mt' => 'Maltese',
        'mn' => 'Mongolian',
        'ne' => 'Nepalia',
        'no' => 'Norwegian',
        'fa' => 'Persian',
        'pa' => 'Punjabi',
        'sr' => 'Serbian',
        'so' => 'Somali',
        'sw' => 'Swahili',
        'uk' => 'Ukranian',
        'yi' => 'Yiddish',
    );
}

function translate_time_str($str, $output_mod) {
    $parts = explode(',', $str);
    $res = array();
    foreach ($parts as $part) {
        $part = trim($part);
        if (preg_match("/(\d+)/", $part, $matches)) {
            $res[] = sprintf($output_mod->trans(preg_replace("/(\d+)/", '%d', $part)), $matches[1]);
        }
    }
    if (!empty($res)) {
        return implode(', ', $res);
    }
    return $str;
}

function list_controls($refresh_link, $config_link, $source_link=false) {
    return '<div class="list_controls">'.
        $refresh_link.$source_link.$config_link.'</div>';
}

function list_sources($sources, $output_mod) {
    $res = '<div class="list_sources">';
    $res .= '<div class="src_title">'.$output_mod->html_safe('Sources').'</div>';
    foreach ($sources as $src) {
        if ($src['type'] == 'imap' && !array_key_exists('folder', $src)) {
            $folder = '_INBOX';
        }
        elseif (!array_key_exists('folder', $src)) {
            $folder = '';
        }
        else {
            $folder = '_'.$src['folder'];
        }
        $res .= '<div class="list_src">'.
            '<a class="del_src_link" href="#" data-id="'.$output_mod->html_safe(sprintf('%s_%s%s', $src['type'], $src['id'], $folder)).'">X</a>'.
            $output_mod->html_safe($src['type']).' '.$output_mod->html_safe($src['name']);
        $res .= ' '.$output_mod->html_safe(str_replace('_', '', $folder));
        $res .= '</div>';
    }
    $res .= '<a href="#" class="add_src_link">Add</a>';
    $res .= '</div>';
    return $res;
}

function format_data_sources($array, $output_mod) {
    $objects = array();
    foreach ($array as $values) {
        $items = array();
        foreach ($values as $name => $value) {
            if ($name == 'callback') {
                $items[] = $output_mod->html_safe($name).':'.$output_mod->html_safe($value);
            }
            else {
                $items[] = $output_mod->html_safe($name).':"'.$output_mod->html_safe($value).'"';
            }
        }
        $objects[] = '{'.implode(',', $items).'}';
    }
    return '['.implode(',', $objects).']';
}

function validate_search_terms($terms) {
    $terms = trim(strip_tags($terms));
    if (!$terms) {
        $terms = false;
    }
    return $terms;
}

function validate_search_fld($fld) {
    if (in_array($fld, array('TEXT', 'BODY', 'FROM', 'SUBJECT'))) {
        return $fld;
    }
    return false;
}

function search_field_selection($current, $output_mod) {
    $flds = array(
        'TEXT' => 'Entire message',
        'BODY' => 'Message body',
        'SUBJECT' => 'Subject',
        'FROM' => 'From',
    );
    $res = '<select id="search_fld" name="search_fld">';
    foreach ($flds as $val => $name) {
        $res .= '<option ';
        if ($current == $val) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="'.$val.'">'.$output_mod->trans($name).'</option>';
    }
    $res .= '</select>';
    return $res;
}

function email_is_active($mod_list) {
    if (stristr($mod_list, 'imap') && stristr($mod_list, 'pop3')) {
        return true;
    }
    return false;
}

?>
