<?php

namespace Mvnaz\ImapConnector\Horde;

class SearchQuery extends \Horde_Imap_Client_Search_Query
{
    public function greaterThan($uid)
    {
        $this->_search['greaterThan'] = "UID {$uid}:*";
    }

    public function build($exts = array())
    {
        /* @todo: BC */
        if (is_array($exts)) {
            $tmp = new \Horde_Imap_Client_Data_Capability_Imap();
            foreach ($exts as $key => $val) {
                $tmp->add($key, is_array($val) ? $val : null);
            }
            $exts = $tmp;
        } elseif (!is_null($exts)) {
            if ($exts instanceof \Horde_Imap_Client_Base) {
                $exts = $exts->capability;
            } elseif (!($exts instanceof \Horde_Imap_Client_Data_Capability)) {
                throw new \InvalidArgumentException('Incorrect $exts parameter');
            }
        }

        $temp = array(
            'cmds' => new \Horde_Imap_Client_Data_Format_List(),
            'exts' => $exts,
            'exts_used' => array()
        );
        $cmds = &$temp['cmds'];
        $charset = $charset_cname = null;
        $default_search = true;
        $exts_used = &$temp['exts_used'];
        $ptr = &$this->_search;

        $charset_get = function ($c) use (&$charset, &$charset_cname) {
            $charset = is_null($c)
                ? 'US-ASCII'
                : strval($c);
            $charset_cname = ($charset === 'US-ASCII')
                ? 'Horde_Imap_Client_Data_Format_Astring'
                : 'Horde_Imap_Client_Data_Format_Astring_Nonascii';
        };
        $create_return = function ($charset, $exts_used, $cmds) {
            return array(
                'charset' => $charset,
                'exts' => array_keys(array_flip($exts_used)),
                'query' => $cmds
            );
        };

        /* Do IDs check first. If there is an empty ID query (without a NOT
         * qualifier), the rest of this query is irrelevant since we already
         * know the search will return no results. */
        if (isset($ptr['ids'])) {
            if (!count($ptr['ids']['ids']) && !$ptr['ids']['ids']->special) {
                if (empty($ptr['ids']['not'])) {
                    /* This is a match on an empty list of IDs. We do need to
                     * process any OR queries that may exist, since they are
                     * independent of this result. */
                    if (isset($ptr['or'])) {
                        $this->_buildAndOr(
                            'OR', $ptr['or'], $charset, $exts_used, $cmds
                        );
                    }
                    return $create_return($charset, $exts_used, $cmds);
                }

                /* If reached here, this a NOT search of an empty list. We can
                 * safely discard this from the output. */
            } else {
                $this->_addFuzzy(!empty($ptr['ids']['fuzzy']), $temp);
                if (!empty($ptr['ids']['not'])) {
                    $cmds->add('NOT');
                }
                if (!$ptr['ids']['ids']->sequence) {
                    $cmds->add('UID');
                }
                $cmds->add(strval($ptr['ids']['ids']));
            }
        }

        if (isset($ptr['new'])) {
            $this->_addFuzzy(!empty($ptr['newfuzzy']), $temp);
            if ($ptr['new']) {
                $cmds->add('NEW');
                unset($ptr['flag']['UNSEEN']);
            } else {
                $cmds->add('OLD');
            }
            unset($ptr['flag']['RECENT']);
        }

        if (isset($ptr['greaterThan'])) {
            $cmds->add($ptr['greaterThan']);
        }

        if (!empty($ptr['flag'])) {
            foreach ($ptr['flag'] as $key => $val) {
                $this->_addFuzzy(!empty($val['fuzzy']), $temp);

                $tmp = '';
                if (empty($val['set'])) {
                    // This is a 'NOT' search.  All system flags but \Recent
                    // have 'UN' equivalents.
                    if ($key == 'RECENT') {
                        $cmds->add('NOT');
                    } else {
                        $tmp = 'UN';
                    }
                }

                if ($val['type'] == 'keyword') {
                    $cmds->add(array(
                        $tmp . 'KEYWORD',
                        $key
                    ));
                } else {
                    $cmds->add($tmp . $key);
                }
            }
        }

        if (!empty($ptr['header'])) {
            /* The list of 'system' headers that have a specific search
             * query. */
            $systemheaders = array(
                'BCC', 'CC', 'FROM', 'SUBJECT', 'TO'
            );

            foreach ($ptr['header'] as $val) {
                $this->_addFuzzy(!empty($val['fuzzy']), $temp);

                if (!empty($val['not'])) {
                    $cmds->add('NOT');
                }

                if (in_array($val['header'], $systemheaders)) {
                    $cmds->add($val['header']);
                } else {
                    $cmds->add(array(
                        'HEADER',
                        new \Horde_Imap_Client_Data_Format_Astring($val['header'])
                    ));
                }

                $charset_get($this->_charset);
                $cmds->add(
                    new $charset_cname(isset($val['text']) ? $val['text'] : '')
                );
            }
        }

        if (!empty($ptr['text'])) {
            foreach ($ptr['text'] as $val) {
                $this->_addFuzzy(!empty($val['fuzzy']), $temp);

                if (!empty($val['not'])) {
                    $cmds->add('NOT');
                }

                $charset_get($this->_charset);
                $cmds->add(array(
                    $val['type'],
                    new $charset_cname($val['text'])
                ));
            }
        }

        if (!empty($ptr['size'])) {
            foreach ($ptr['size'] as $key => $val) {
                $this->_addFuzzy(!empty($val['fuzzy']), $temp);
                if (!empty($val['not'])) {
                    $cmds->add('NOT');
                }
                $cmds->add(array(
                    $key,
                    new \Horde_Imap_Client_Data_Format_Number(
                        empty($val['size']) ? 0 : $val['size']
                    )
                ));
            }
        }

        if (!empty($ptr['date'])) {
            foreach ($ptr['date'] as $val) {
                $this->_addFuzzy(!empty($val['fuzzy']), $temp);

                if (!empty($val['not'])) {
                    $cmds->add('NOT');
                }

                if (empty($val['header'])) {
                    $cmds->add($val['range']);
                } else {
                    $cmds->add('SENT' . $val['range']);
                }
                $cmds->add($val['date']);
            }
        }

        if (!empty($ptr['within'])) {
            if (is_null($exts) || $exts->query('WITHIN')) {
                $exts_used[] = 'WITHIN';
            }

            foreach ($ptr['within'] as $key => $val) {
                $this->_addFuzzy(!empty($val['fuzzy']), $temp);
                if (!empty($val['not'])) {
                    $cmds->add('NOT');
                }

                if (is_null($exts) || $exts->query('WITHIN')) {
                    $cmds->add(array(
                        $key,
                        new \Horde_Imap_Client_Data_Format_Number($val['interval'])
                    ));
                } else {
                    // This workaround is only accurate to within 1 day, due
                    // to limitations with the IMAP4rev1 search commands.
                    $cmds->add(array(
                        ($key == self::INTERVAL_OLDER) ? self::DATE_BEFORE : self::DATE_SINCE,
                        new \Horde_Imap_Client_Data_Format_Date('now -' . $val['interval'] . ' seconds')
                    ));
                }
            }
        }

        if (!empty($ptr['modseq'])) {
            if (!is_null($exts) && !$exts->query('CONDSTORE')) {
                throw new \Horde_Imap_Client_Exception_NoSupportExtension('CONDSTORE');
            }

            $exts_used[] = 'CONDSTORE';

            $this->_addFuzzy(!empty($ptr['modseq']['fuzzy']), $temp);

            if (!empty($ptr['modseq']['not'])) {
                $cmds->add('NOT');
            }
            $cmds->add('MODSEQ');
            if (isset($ptr['modseq']['name'])) {
                $cmds->add(array(
                    new \Horde_Imap_Client_Data_Format_String($ptr['modseq']['name']),
                    $ptr['modseq']['type']
                ));
            }
            $cmds->add(new \Horde_Imap_Client_Data_Format_Number($ptr['modseq']['value']));
        }

        if (isset($ptr['prevsearch'])) {
            if (!is_null($exts) && !$exts->query('SEARCHRES')) {
                throw new \Horde_Imap_Client_Exception_NoSupportExtension('SEARCHRES');
            }

            $exts_used[] = 'SEARCHRES';

            $this->_addFuzzy(!empty($ptr['prevsearchfuzzy']), $temp);

            if (!$ptr['prevsearch']) {
                $cmds->add('NOT');
            }
            $cmds->add('$');
        }

        // Add AND'ed queries
        if (!empty($ptr['and'])) {
            $default_search = $this->_buildAndOr(
                'AND', $ptr['and'], $charset, $exts_used, $cmds
            );
        }

        // Add OR'ed queries
        if (!empty($ptr['or'])) {
            $default_search = $this->_buildAndOr(
                'OR', $ptr['or'], $charset, $exts_used, $cmds
            );
        }

        // Default search is 'ALL'
        if ($default_search && !count($cmds)) {
            $cmds->add('ALL');
        }

        return $create_return($charset, $exts_used, $cmds);
    }
}