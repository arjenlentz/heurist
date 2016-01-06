<?php

/**
* flathml.php:  flattened version of HML - records are not generated redundantly but are indicated by references within other records.
*               $hunifile indicates special one-file-per-record + manifest file for HuNI (huni.net.au)
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


/**
*
* Function list:
* - makeTag()
* - openTag()
* - closeTag()
* - openCDATA()
* - closeCDATA()
* - get_user_id()
* - get_user_name()
* - get_user_username()
* - get_group_ids()
* - is_admin()
* - is_logged_in()
* - findPointers()
* - findReversePointers()
* - findRelatedRecords()
* - buildGraphStructure()
* - outputRecords()
* - outputRecord()
* - outputXInclude()
* - outputRecordStub()
* - makeFileContentNode()
* - outputDetail()
* - outputTemporalDetail()
* - outputDateDetail()
* - outputTDateDetail()
* - outputDurationDetail()
* - replaceIllegalChars()
* - check()
*
* No Classes in this File
*
*/

if (@$argv) {
    // handle command-line queries
    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[$argv[$i]] = $argv[$i + 1];
                ++$i;
            } else {
                $ARGV[$argv[$i]] = true;
            }
        } else {
            array_push($ARGV, $argv[$i]);
        }
    }
    if (@$ARGV['-db']) $_REQUEST["db"] = $ARGV['-db'];
    if (@$ARGV['-f']) $_REQUEST['f'] = $ARGV['-f'];
    $_REQUEST['q'] = @$ARGV['-q'];
    $_REQUEST['w'] = @$ARGV['-w'] ? $ARGV['-w'] : 'a'; // default to ALL RESOURCES
    if (@$ARGV['-stype']) $_REQUEST['stype'] = $ARGV['-stype'];
    $_REQUEST['style'] = '';
    $_REQUEST['depth'] = @$ARGV['-depth'] ? $ARGV['-depth'] : 0;
    if (@$ARGV['-rev']) $_REQUEST['rev'] = $ARGV['-rev'];
    if (@$ARGV['-woot']) $_REQUEST['woot'] = $ARGV['-woot'];
    if (@$ARGV['-stub']) $_REQUEST['stub'] = '1';
    if (@$ARGV['-fc']) $_REQUEST['fc'] = '1'; // inline file content
    if (@$ARGV['-file']) $_REQUEST['file'] = '1'; // inline file content

}

// Note: applyCredentils.php is listed later in the file
require_once (dirname(__FILE__) . '/../../common/config/initialise.php');
require_once (dirname(__FILE__) . '/../../search/getSearchResults.php');
require_once (dirname(__FILE__) . '/../../common/php/getRecordInfoLibrary.php');
require_once (dirname(__FILE__) . '/../../records/woot/woot.php');
require_once (dirname(__FILE__) . '/../../records/files/fileUtils.php');

if(@$_REQUEST['file']){ // output manifest + files ??
    $intofile = true;
}else{
    $intofile = false;
}

$hunifile = null; //flag one-record-per-file mode for HuNI
// why is this left to be set later as a success on file access??

if(!$intofile){
    if (@$_REQUEST['mode'] != '1') {
        header('Content-type: text/xml; charset=utf-8');
    }
    echo "<?xml version='1.0' encoding='UTF-8'?>\n";
}


set_time_limit(0); //no limit

mysql_connection_select(DATABASE);

$relRT = (defined('RT_RELATION') ? RT_RELATION : 0);
$relTypDT = (defined('DT_RELATION_TYPE') ? DT_RELATION_TYPE : 0);
$relSrcDT = (defined('DT_PRIMARY_RESOURCE') ? DT_PRIMARY_RESOURCE : 0);
$relTrgDT = (defined('DT_TARGET_RESOURCE') ? DT_TARGET_RESOURCE : 0);


// Determine database registration ID
$res = mysql_query("select sys_dbRegisteredID, sys_dbName, sys_dbDescription, sys_OwnerGroupID ".
    "from sysIdentification where 1");
if ($res) {
    $row = mysql_fetch_row($res); // Get system information for current database
    $dbID = $row[0];
}


//----------------------------------------------------------------------------//
//  Tag construction helpers
//----------------------------------------------------------------------------//

function output($str){
    global $intofile, $hunifile;

    if($intofile){
        if($hunifile){
            fwrite($hunifile, $str);
        }
    }else{
        echo $str;
    }

}


function makeTag($name, $attributes = null, $textContent = null, $close = true, $encodeContent = true) {
    $tag = "<$name";
    if (is_array($attributes)) {
        foreach ($attributes as $attr => $value) {
            $tag.= ' ' . htmlspecialchars($attr) . '="' . htmlspecialchars($value) . '"';
        }
    }
    if ($close && !$textContent) {
        $tag.= '/>';
    } else {
        $tag.= '>';
    }
    if ($textContent) {
        if ($encodeContent) {
            $tag.= htmlspecialchars($textContent);
        } else {
            $tag.= $textContent;
        }
        $tag.= "</$name>";
    }
    output( $tag . "\n" );

}


/**
* description
* @param     mixed $name
* @param     mixed $attributes
* @return    type description
*/
function openTag($name, $attributes = null) {
    makeTag($name, $attributes, null, false);
}


function closeTag($name) {
    output( "</$name>\n" );

}


function openCDATA() {
    output( "<![CDATA[\n" );
}


function closeCDATA() {
    output( "]]>\n" );
}


//----------------------------------------------------------------------------//
//  Retrieve record- and detail- type metadata
//----------------------------------------------------------------------------//

$RTN = array(); //record type name
$RQS = array(); //record type specific detail name
$DTN = array(); //detail type name
$DTT = array(); //detail type base type
$INV = array(); //relationship term inverse
$WGN = array(); //work group name
$UGN = array(); //User name
$TL = array(); //term lookup
$TLV = array(); //term lookup by value

// record type labels
$query = 'SELECT rty_ID, rty_Name FROM defRecTypes';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
    $RTN[$row['rty_ID']] = $row['rty_Name'];
    foreach (getRectypeFields($row['rty_ID']) as $rst_DetailTypeID => $rdr) {
        // type-specific names for detail types
        $RQS[$row['rty_ID']][$rst_DetailTypeID] = @$rdr[0];
    }
}

// base names, varieties for detail types
$query = 'SELECT dty_ID, dty_Name, dty_Type FROM defDetailTypes';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
    $DTN[$row['dty_ID']] = $row['dty_Name'];
    $DTT[$row['dty_ID']] = $row['dty_Type'];
}
$INV = mysql__select_assoc('defTerms', //saw Enum change just assoc id to related id
    'trm_ID', 'trm_InverseTermID', '1');

// lookup detail type enum values
$query = 'SELECT trm_ID, trm_Label, trm_ParentTermID, trm_OntID, trm_Code FROM defTerms';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
    $TL[$row['trm_ID']] = $row;
    $TLV[$row['trm_Label']] = $row;
}

// group names
mysql_connection_select(USERS_DATABASE) or die(mysql_error());
$WGN = mysql__select_assoc('sysUGrps grp', 'grp.ugr_ID', 'grp.ugr_Name', "ugr_Type ='workgroup'");
$UGN = mysql__select_assoc('sysUGrps grp', 'grp.ugr_ID', 'grp.ugr_Name', "ugr_Type ='user'");
mysql_connection_select(DATABASE) or die(mysql_error());
$GEO_TYPES = array('r' => 'bounds', 'c' => 'circle', 'pl' => 'polygon', 'l' => 'path', 'p' => 'point');

// set parameter defaults
$REVERSE = @$_REQUEST['rev'] === 'no' ? false : true; //default to including reverse pointers
$EXPAND_REV_PTR = @$_REQUEST['revexpand'] === 'no' ? false : true;
$WOOT = @$_REQUEST['woot'] ? intval($_REQUEST['woot']) : 0; //default to not output text content
$USEXINCLUDELEVEL = array_key_exists('hinclude', $_REQUEST) && is_numeric($_REQUEST['hinclude']) ? $_REQUEST['hinclude'] : 99;
//default to not output xinclude format for related records until beyound 99 degrees of separation
$USEXINCLUDE = array_key_exists('hinclude', $_REQUEST) ? true : false; //default to not output xinclude format for related records
$OUTPUT_STUBS = @$_REQUEST['stub'] == '1' ? true : false; //default to not output stubs
$INCLUDE_FILE_CONTENT = (@$_REQUEST['fc'] && $_REQUEST['fc'] == - 1 ? false : (@$_REQUEST['fc'] && is_numeric($_REQUEST['fc']) ? $_REQUEST['fc'] : 0)); // default to expand xml file content
//TODO: supress loopback by default unless there is a filter.
$SUPRESS_LOOPBACKS = (@$_REQUEST['slb'] && $_REQUEST['slb'] == 0 ? false : true); // default to supress loopbacks or gives oneside of a relationship record
$FRESH = (@$_REQUEST['f'] && $_REQUEST['f'] == 1 ? true : false);
//$PUBONLY = (((@$_REQUEST['pub_ID'] && is_numeric($_REQUEST['pub_ID'])) ||
//			(@$_REQUEST['pubonly'] && $_REQUEST['pubonly'] > 0)) ? true :false);
$PUBONLY = ((@$_REQUEST['pubonly'] && $_REQUEST['pubonly'] > 0) ? true : (!is_logged_in() ? true : false));

$filterString = (@$_REQUEST['rtfilters'] ? $_REQUEST['rtfilters'] : null);
if ($filterString && preg_match('/[^\\:\\s"\\[\\]\\{\\}0-9\\,]/', $filterString)) {
    die(" error invalid json record type filters string");
}

$RECTYPE_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($RECTYPE_FILTERS)) {
    die(" error decoding json record type filters string");
}

$filterString = (@$_REQUEST['relfilters'] ? $_REQUEST['relfilters'] : null);
if ($filterString && preg_match('/[^\\:\\s"\\[\\]\\{\\}0-9\\,]/', $filterString)) {
    die(" error invalid json relation type filters string");
}

$RELTYPE_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($RELTYPE_FILTERS)) {
    die(" error decoding json relation type filters string");
}

$filterString = (@$_REQUEST['ptrfilters'] ? $_REQUEST['ptrfilters'] : null);
if ($filterString && preg_match('/[^\\:\\s"\\[\\]\\{\\}0-9\\,]/', $filterString)) {
    die(" error invalid json pointer type filters string");
}

$PTRTYPE_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($PTRTYPE_FILTERS)) {
    die(" error decoding json pointer type filters string");
}

$filterString = (@$_REQUEST['selids'] ? $_REQUEST['selids'] : null);
if ($filterString && preg_match('/[^\\:\\s"\\[\\]\\{\\}0-9\\,]/', $filterString)) {
    die(" error invalid json record type filters string");
}

$SELIDS_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($SELIDS_FILTERS)) {
    die(" error decoding json selected ids string");
} else {
    $selectedIDs = array();
    foreach ($SELIDS_FILTERS as $ids) {
        foreach ($ids as $id) {
            $selectedIDs[$id] = 1;
        }
    }
    $selectedIDs = array_keys($selectedIDs);
}

$MAX_DEPTH = (@$_REQUEST['depth'] ? intval($_REQUEST['depth']) : (count(array_merge(array_keys($PTRTYPE_FILTERS), array_keys($RELTYPE_FILTERS), array_keys($RECTYPE_FILTERS), array_keys($SELIDS_FILTERS))) > 0 ? max(array_merge(array_keys($PTRTYPE_FILTERS), array_keys($RELTYPE_FILTERS), array_keys($RECTYPE_FILTERS), array_keys($SELIDS_FILTERS))) : 0)); // default to only one level

// handle special case for collection where ids are stored in teh session.
if (array_key_exists('q', $_REQUEST)) {
    if (preg_match('/_COLLECTED_/', $_REQUEST['q'])) {
        if (!session_id()) session_start();
        $collection = & $_SESSION[HEURIST_SESSION_DB_PREFIX . 'heurist']['record-collection'];
        if (count($collection) > 0) {
            $_REQUEST['q'] = 'ids:' . join(',', array_keys($collection));
        } else {
            $_REQUEST['q'] = '';
        }
    }
} else if (array_key_exists('recID', $_REQUEST)) { //record IDs to use as a query
    //check for expansion of query records.
    $recIDs = explode(",", $_REQUEST['recID']);
    $_REQUEST['q'] = 'ids:' . join(',', $recIDs);
}

//----------------------------------------------------------------------------//
//  Authentication
//----------------------------------------------------------------------------//

if (@$ARGV) { // commandline actuation
    function get_user_id() {
        return 0;
    }
    function get_user_name() {
        return '';
    }
    function get_user_username() {
        return '';
    }
    function get_group_ids() {
        return array(0);
    }
    function is_admin() {
        return false;
    }
    function is_logged_in() {
        return false;
    }
    $ss_id = 0;
} else { // loggin required entry
    $ss_id = 0;
    require_once (dirname(__FILE__) . '/../../common/connect/applyCredentials.php');
}

$ACCESSABLE_OWNER_IDS = mysql__select_array(
    'sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID', 'ugl_UserID='
    . get_user_id() . ' and grp.ugr_Type != "user" order by ugl_GroupID');


if (is_logged_in()) {
    array_push($ACCESSABLE_OWNER_IDS, get_user_id());
    if (!in_array(0, $ACCESSABLE_OWNER_IDS)) {
        array_push($ACCESSABLE_OWNER_IDS, 0);
    }
}


//----------------------------------------------------------------------------//
// Traversal functions
// The aim here is to bundle all the queries for each level of relationships
// into one query, rather than doing them all recursively.
//----------------------------------------------------------------------------//

/**
* findPointers - Helper function that finds recIDs of record pointer details for all records in a given set of recIDs
* which can be filtered to a set of rectypes
* @author Stephen White derived from original work by Kim Jackson
* @param $rec_ids an array of recIDs from the Records table for which to search from details
* @param $rtyIDs an array of rectypeIDs valid in the defRecTypes table used to filter the result
* @param $dtyIDs an array of detailTypeIDs valid in the defDetailTypes table used to filter the results
* @return $ret a comma separated list of recIDs
*/
function findPointers($qrec_ids, &$recSet, $depth, $rtyIDs, $dtyIDs) {
    global $ACCESSABLE_OWNER_IDS, $PUBONLY;
    //saw TODO add error checking for numeric values in $rtyIDs and $dtyIDs
    // find all detail values for resource type details which exist for any record with an id in $rec_ids
    // and also is of a type in rtyIDs if rtyIDs is set to non null
    $nlrIDs = array(); // new linked record IDs
    $query = 'SELECT dtl_RecID as srcRecID, src.rec_RecTypeID as srcType, ' . 'dtl_Value as trgRecID, '
    . 'dtl_DetailTypeID as ptrDetailTypeID ' . ', trg.* ' . ', trg.rec_NonOwnerVisibility ' .
    //saw TODO check if we need to also check group ownership
    'FROM recDetails LEFT JOIN defDetailTypes on dtl_DetailTypeID = dty_ID ' .
    'LEFT JOIN Records src on src.rec_ID = dtl_RecID ' .
    'LEFT JOIN Records trg on trg.rec_ID = dtl_Value ' .
    'WHERE dtl_RecID in (' . join(',', $qrec_ids) . ') ' .
    ($rtyIDs && count($rtyIDs) > 0 ? 'AND trg.rec_RecTypeID in (' . join(',', $rtyIDs) . ') ' : '') .
    ($dtyIDs && count($dtyIDs) > 0 ? 'AND dty_ID in (' . join(',', $dtyIDs) . ') ' : '')
    . 'AND dty_Type = "resource" AND ' . (count($ACCESSABLE_OWNER_IDS) > 0 && !$PUBONLY ? '(trg.rec_OwnerUGrpID in (' .
        join(',', $ACCESSABLE_OWNER_IDS) . ') OR ' : '(') .
    ((is_logged_in() && !$PUBONLY) ? 'NOT trg.rec_NonOwnerVisibility = "hidden")' : 'trg.rec_NonOwnerVisibility = "public")');

    $res = mysql_query($query);

    while ($res && $row = mysql_fetch_assoc($res)) {
        // if target is not in the result
        if (!array_key_exists($row['trgRecID'], $recSet['relatedSet'])) {
            $recSet['relatedSet'][$row['trgRecID']] = array('depth' => $depth, 'recID' => $row['trgRecID']);
            $nlrIDs[$row['trgRecID']] = 1; //save it for next level query

        } else if ($rtyIDs || $dtyIDs) { // TODO placed here for directed filtering which means we want repeats to be expanded
            $nlrIDs[$row['trgRecID']] = 1; //save it for next level query

        }

        if (!@$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']) {
            $recSet['relatedSet'][$row['trgRecID']]['revPtrLinks'] = array('byInvDtlType' => array(), 'byRecIDs' => array()); //create an entry

        }

        if (!@$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']]) {
            $recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']] = array($row['srcRecID']);
        } else if (!in_array($row['srcRecID'], $recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']])) {
            array_push($recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']], $row['srcRecID']);
        }

        if (!@$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']]) {
            $recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']] = array($row['ptrDetailTypeID']);
        } else if (!in_array($row['ptrDetailTypeID'], $recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']])) {
            array_push($recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']], $row['ptrDetailTypeID']);
        }

        if (!@$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']) {
            $recSet['relatedSet'][$row['srcRecID']]['ptrLinks'] = array('byDtlType' => array(), 'byRecIDs' => array()); //create ptrLinks sub arrays

        }

        if (!@$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']]) {
            $recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']] = array($row['trgRecID']);
        } else if (!in_array($row['trgRecID'], $recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']])) {
            array_push($recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']], $row['trgRecID']);
        }

        if (!@$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']]) {
            $recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']] = array($row['ptrDetailTypeID']);
        } else if (!in_array($row['ptrDetailTypeID'], $recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']])) {
            array_push($recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']], $row['ptrDetailTypeID']);
        }
    }
    return array_keys($nlrIDs);
    //	return $rv;
}


/**
* findReversePointers - Helper function that finds recIDs of all records excluding relationShip records
* that have a pointer detail value that is in a given set of recIDs which can be filtered to a set of rectypes
* @author Kim Jackson
* @author Stephen White
* @param $rec_ids an array of recIDs from the Records table for which to search from details
* @param $pointers an out array of recIDs to store look up detailTypeID by pointed_to recID by pointed_to_by recID
* @param $rtyIDs an array of rectypeIDs valid in the defRecTypes table
* @param $dtyIDs an array of detailTypeIDs valid in the defDetailTypes table used to filter the results
* @return $ret a comma separated list of recIDs of pointed_to_by records
*/
function findReversePointers($qrec_ids, &$recSet, $depth, $rtyIDs, $dtyIDs) {
    global $REVERSE, $ACCESSABLE_OWNER_IDS, $relRT, $PUBONLY;
    //if (!$REVERSE) return array();
    $nlrIDs = array(); // new linked record IDs
    $query = 'SELECT dtl_Value as srcRecID, src.rec_RecTypeID as srcType, ' .
    'dtl_RecID as trgRecID, dty_ID as ptrDetailTypeID ' . ', trg.* ' . ', trg.rec_NonOwnerVisibility ' .
    'FROM recDetails ' . 'LEFT JOIN defDetailTypes ON dtl_DetailTypeID = dty_ID ' .
    'LEFT JOIN Records trg on trg.rec_ID = dtl_RecID ' . 'LEFT JOIN Records src on src.rec_ID = dtl_Value ' .
    'WHERE dty_Type = "resource" ' . 'AND dtl_Value IN (' .
    join(',', $qrec_ids) . ') ' . ($rtyIDs && count($rtyIDs) > 0 ? 'AND trg.rec_RecTypeID in (' .
        join(',', $rtyIDs) . ') ' : '') . ($dtyIDs && count($dtyIDs) > 0 ? 'AND dty_ID in (' .
        join(',', $dtyIDs) . ') ' : '') . "AND trg.rec_RecTypeID != $relRT AND " .
    (count($ACCESSABLE_OWNER_IDS) > 0 && !$PUBONLY ? '(trg.rec_OwnerUGrpID in (' .
        join(',', $ACCESSABLE_OWNER_IDS) . ') OR ' : '(') .
    (is_logged_in() && !$PUBONLY ? 'NOT trg.rec_NonOwnerVisibility = "hidden")' : 'trg.rec_NonOwnerVisibility = "public")');

    $res = mysql_query($query);

    while ($res && $row = mysql_fetch_assoc($res)) {
        // if target is not in the result
        $nlrIDs[$row['trgRecID']] = 1; //save it for next level query
        if (!array_key_exists($row['trgRecID'], $recSet['relatedSet'])) {
            $recSet['relatedSet'][$row['trgRecID']] = array('depth' => $depth, 'recID' => $row['trgRecID']);
            $nlrIDs[$row['trgRecID']] = 1; //save it for next level query

        } else if ($rtyIDs || $dtyIDs) { // TODO placed here for directed filtering which means we want repeats to be expanded
            $nlrIDs[$row['trgRecID']] = 1; //save it for next level query

        }
        if (!@$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']) {
            $recSet['relatedSet'][$row['trgRecID']]['ptrLinks'] = array('byDtlType' => array(), 'byRecIDs' => array()); //create an entry

        }

        if (!@$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']]) {
            $recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']] = array($row['srcRecID']);
        } else if (!in_array($row['srcRecID'], $recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']])) {
            array_push($recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']], $row['srcRecID']);
        }

        if (!@$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']]) {
            $recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']] = array($row['ptrDetailTypeID']);
        } else if (!in_array($row['ptrDetailTypeID'], $recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']])) {
            array_push($recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']], $row['ptrDetailTypeID']);
        }

        if (!@$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']) {
            $recSet['relatedSet'][$row['srcRecID']]['revPtrLinks'] = array('byInvDtlType' => array(), 'byRecIDs' => array()); //create an entry

        }

        if (!@$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']]) {
            $recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']] = array($row['trgRecID']);
        } else if (!in_array($row['trgRecID'], $recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']])) {
            array_push($recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']], $row['trgRecID']);
        }

        if (!@$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']]) {
            $recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']] = array($row['ptrDetailTypeID']);
        } else if (!in_array($row['ptrDetailTypeID'], $recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']])) {
            array_push($recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']], $row['ptrDetailTypeID']);
        }
    }
    return array_keys($nlrIDs);
}


/**
* findRelatedRecords - Helper function that finds recIDs of all related records using relationShip records
* that have a pointer detail value that is in a given set of recIDs which can be filtered to a set of rectypes
* @author Kim Jackson
* @author Stephen White
* @param $rec_ids an array of recIDs from the Records table for which to search related records
* @param $relationships an out array of recIDs to store look up relationRecID by supplied target/source recIDs
* @param $rtyIDs an array of rectypeIDs valid in the defRecTypes table
* @param $relTermIDs an array of termIDs valid in the defTerms table used to filter the results
* @return $ret a comma separated list of recIDs of other record recIDs
*/
function findRelatedRecords($qrec_ids, &$recSet, $depth, $rtyIDs, $relTermIDs) {
    global $REVERSE, $ACCESSABLE_OWNER_IDS, $relRT, $relTrgDT, $relTypDT, $relSrcDT, $PUBONLY;
    $nlrIDs = array();
    $query = 'SELECT f.dtl_Value as srcRecID, rel.rec_ID as relID, ' . // from detail
    'IF( f.dtl_Value IN (' . join(',', $qrec_ids) . '),1,0) as srcIsFrom, ' .
    't.dtl_Value as trgRecID, trm.trm_ID as relType, trm.trm_InverseTermId as invRelType ' .
    ', src.rec_NonOwnerVisibility , trg.rec_NonOwnerVisibility ' . 'FROM recDetails f ' .
    'LEFT JOIN Records rel ON rel.rec_ID = f.dtl_RecID and f.dtl_DetailTypeID = ' . $relSrcDT . ' ' .
    'LEFT JOIN recDetails t ON t.dtl_RecID = rel.rec_ID and t.dtl_DetailTypeID = ' . $relTrgDT . ' ' .
    'LEFT JOIN recDetails r ON r.dtl_RecID = rel.rec_ID and r.dtl_DetailTypeID = ' . $relTypDT . ' ' .
    'LEFT JOIN defTerms trm ON trm.trm_ID = r.dtl_Value ' . 'LEFT JOIN Records trg ON trg.rec_ID = t.dtl_Value ' .
    'LEFT JOIN Records src ON src.rec_ID = f.dtl_Value ' .
    'WHERE rel.rec_RecTypeID = ' . $relRT . ' ' . 'AND (f.dtl_Value IN (' . join(',', $qrec_ids) . ') '.
    ($rtyIDs && count($rtyIDs) > 0 ? 'AND trg.rec_RecTypeID in (' . join(',', $rtyIDs) . ') ' : '') .
    ($REVERSE ? 'OR t.dtl_Value IN (' . join(',', $qrec_ids) . ') ' .
        ($rtyIDs && count($rtyIDs) > 0 ? 'AND src.rec_RecTypeID in (' .
            join(',', $rtyIDs) . ') ' : '') : '') . ')' .
    (count($ACCESSABLE_OWNER_IDS) > 0 && !$PUBONLY ? 'AND (src.rec_OwnerUGrpID in (' .
        join(',', $ACCESSABLE_OWNER_IDS) . ') OR ' : 'AND (') .
    ((is_logged_in() && !$PUBONLY) ? 'NOT src.rec_NonOwnerVisibility = "hidden")' : 'src.rec_NonOwnerVisibility = "public")') .
    (count($ACCESSABLE_OWNER_IDS) > 0 && !$PUBONLY ? 'AND (trg.rec_OwnerUGrpID in (' .
        join(',', $ACCESSABLE_OWNER_IDS) . ') OR ' : 'AND (').
    (is_logged_in() && !$PUBONLY ? 'NOT trg.rec_NonOwnerVisibility = "hidden")' : 'trg.rec_NonOwnerVisibility = "public")').
    ($relTermIDs && count($relTermIDs) > 0 ? 'AND (trm.trm_ID in (' .
        join(',', $relTermIDs) . ') OR trm.trm_InverseTermID in (' . join(',', $relTermIDs) . ')) ' : '');

    $res = mysql_query($query);

    while ($res && $row = mysql_fetch_assoc($res)) {
        if (!$row['relType'] && !$row['invRelType']) { // no type information invalid relationship
            continue;
        }
        //echo "row is ".print_r($row,true);
        // if source is not in the result
        if (!array_key_exists($row['srcRecID'], $recSet['relatedSet'])) {
            $recSet['relatedSet'][$row['srcRecID']] = array('depth' => $depth, 'recID' => $row['srcRecID']);
            $nlrIDs[$row['srcRecID']] = 1; //save it for next level query
        }

        //if rel rec not in result
        if (!array_key_exists($row['relID'], $recSet['relatedSet'])) {
            $recSet['relatedSet'][$row['relID']] = array('depth' => $recSet['relatedSet'][$row['srcRecID']]['depth'] . ".5", 'recID' => $row['relID']);
        }

        // if target is not in the result
        if (!array_key_exists($row['trgRecID'], $recSet['relatedSet'])) {
            $recSet['relatedSet'][$row['trgRecID']] = array('depth' => $depth, 'recID' => $row['trgRecID']);
            $nlrIDs[$row['trgRecID']] = 1; //save it for next level query

        }

        if (!@$recSet['relatedSet'][$row['srcRecID']]['relLinks']) {
            $recSet['relatedSet'][$row['srcRecID']]['relLinks'] =
            array('byRelType' => array(), 'byRecIDs' => array(), 'relRecIDs' => array()); //create an entry
        }

        if (!@$recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']]) {
            $recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']] = array($row['trgRecID']);
        } else if (!in_array($row['trgRecID'], $recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']])) {
            array_push($recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']], $row['trgRecID']);
        }

        if (!@$recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']]) {
            $recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']] = array($row['relType']);
        } else if (!in_array($row['relType'], $recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']])) {
            array_push($recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']], $row['relType']);
        }

        if ($row['relID'] && !in_array($row['relID'], $recSet['relatedSet'][$row['srcRecID']]['relLinks']['relRecIDs'])) {
            array_push($recSet['relatedSet'][$row['srcRecID']]['relLinks']['relRecIDs'], $row['relID']);
        }

        if (!@$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']) {
            $recSet['relatedSet'][$row['trgRecID']]['revRelLinks'] =
            array('byInvRelType' => array(), 'byRecIDs' => array(), 'relRecIDs' => array()); //create an entry
        }

        $inverse = $row['invRelType'] ? $row['invRelType'] : "-" . $row['relType'];
        if (!@$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse]) {
            $recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse] = array($row['srcRecID']);
        } else if (!in_array($row['srcRecID'], $recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse])) {
            array_push($recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse], $row['srcRecID']);
        }

        if (!@$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']]) {
            $recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']] = array($inverse);
        } else if (!in_array($inverse, $recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']])) {
            array_push($recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']], $inverse);
        }

        if ($row['relID'] && !in_array($row['relID'], $recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['relRecIDs'])) {
            array_push($recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['relRecIDs'], $row['relID']);
        }
    }
    return array_keys($nlrIDs);
}


/**
* buildGraph - Function to build the structure for a set of records and all there related(linked) records to a given level
* @author Stephen White
* @param $rec_ids an array of recIDs from the Records table for which to build the tree
* @param $recSet an out array to store look up records by recordID
* @param $relationships an out array of recIDs to store look up relationRecID by supplied target/source recIDs
*/
function buildGraphStructure($rec_ids, &$recSet) {
    global $MAX_DEPTH, $REVERSE, $RECTYPE_FILTERS, $RELTYPE_FILTERS, $PTRTYPE_FILTERS, $EXPAND_REV_PTR, $OUTPUT_STUBS;
    $depth = 0;
    $rtfilter = (array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth] : null);
    if ($rtfilter) {
        $query = 'SELECT rec_ID from Records ' .
        'WHERE rec_ID in (' . join(",", $rec_ids) . ') ' .
        'AND rec_RecTypeID in (' . join(",", $rtfilter) . ')';
        $filteredIDs = array();

        $res = mysql_query($query);

        while ($res && $row = mysql_fetch_row($res)) {
            $filteredIDs[$row[0]] = 1;
        }

        $rec_ids = array_keys($filteredIDs);
    }

    if ($MAX_DEPTH == 0 && $OUTPUT_STUBS && count($rec_ids) > 0) {
        findPointers($rec_ids, $recSet, 1, null, null);
    } else {
        while ($depth++ < $MAX_DEPTH && count($rec_ids) > 0) {
            $rtfilter = (@$RECTYPE_FILTERS && array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth] : null);
            $relfilter = (@$RELTYPE_FILTERS && array_key_exists($depth, $RELTYPE_FILTERS) ? $RELTYPE_FILTERS[$depth] : null);
            $ptrfilter = (@$PTRTYPE_FILTERS && array_key_exists($depth, $PTRTYPE_FILTERS) ? $PTRTYPE_FILTERS[$depth] : null);
            $p_rec_ids = findPointers($rec_ids, $recSet, $depth, $rtfilter, $ptrfilter);
            $rp_rec_ids = $REVERSE ? findReversePointers($rec_ids, $recSet, $depth, $rtfilter, $ptrfilter) : array();
            $rel_rec_ids = findRelatedRecords($rec_ids, $recSet, $depth, $rtfilter, $relfilter);
            $rec_ids = array_merge($p_rec_ids, ($EXPAND_REV_PTR ? $rp_rec_ids : array()), $rel_rec_ids); // record set for a given level

        }
    }
}


//----------------------------------------------------------------------------//
//  Output functions
//----------------------------------------------------------------------------//

/**
* Outputs the set of records as an xml stream or separate files per record (if $intofile set)
*
* @param mixed $result
*/
function outputRecords($result) {

    global $OUTPUT_STUBS, $FRESH, $MAX_DEPTH, $intofile, $hunifile;

    $recSet = array('count' => 0, 'relatedSet' => array());
    $rec_ids = explode(",", $result['recIDs']);

    if (array_key_exists('expandColl', $_REQUEST)) {
        $rec_ids = expandCollections($rec_ids);
    }

    if(count($rec_ids)>1000){
        set_time_limit( 30 * count($rec_ids) % 1000 );
    }

    foreach ($rec_ids as $recID) {
        $recSet['relatedSet'][$recID] = array('depth' => 0, 'recID' => $recID );
    }

    buildGraphStructure($rec_ids, $recSet);
    $recSet['count'] = count($recSet['relatedSet']);

    /* ARTEM: avoid memory overflow
    foreach ($recSet['relatedSet'] as $recID => $recInfo) {
    $recSet['relatedSet'][$recID]['record'] = loadRecord_NoCache($recID, true); //loadRecord($recID, $FRESH, true);
    }
    */

    $resout = array();

    if(!$intofile){
        openTag('records', array('count' => $recSet['count']));
    }

    foreach ($recSet['relatedSet'] as $recID => $recInfo) {
        if (intval($recInfo['depth']) <= $MAX_DEPTH) {

            // output one record - returs rec_RecTypeID for given record
            $res = outputRecord($recInfo, $recSet['relatedSet'], $OUTPUT_STUBS);

            // close the file if using HuNI manifest + separate files output
            // The file is opened in outputRecord but left open!
            if($intofile && $hunifile){
                fclose($hunifile);
            }

            if($res){
                $resout[$recID] = $res; //$recInfo['record']['rec_RecTypeID'];
            }else if ($intofile && file_exists(HEURIST_HML_DIR.$recID.".xml")){
                unlink(HEURIST_HML_DIR.$record['rec_ID'].".xml");
            }
        }
    }

    if(!$intofile){
        closeTag('records');
    }

    return $resout;
}


function outputRecord($recordInfo, $recInfos, $outputStub = false, $parentID = null) {
    global $RTN, $DTN, $INV, $TL, $RQS, $WGN, $UGN, $MAX_DEPTH, $WOOT, $USEXINCLUDELEVEL,
    $RECTYPE_FILTERS, $SUPRESS_LOOPBACKS, $relRT, $relTrgDT, $relTypDT, $relSrcDT, $selectedIDs, $intofile, $hunifile, $dbID;

    $hunifile = null;

    //$record = $recordInfo['record'];
    $record = loadRecord_NoCache($recordInfo['recID'], true); //loadRecord($recID, $FRESH, true);

    $depth = $recordInfo['depth'];
    $filter = (array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth] : null);

    if (isset($filter) && !in_array($record['rec_RecTypeID'], $filter)) {
        if ($record['rec_RecTypeID'] != $relRT) { //not a relationship rectype
            if ($depth > 0) {
                //				if ($USEXINCLUDELEVEL){
                //					outputXInclude($record);
                //				}else
                if ($outputStub) {
                    outputRecordStub($record);
                } else {
                    output( $record['rec_ID'] );
                }
            }
            return false;
        }
    }

    // using separare files per record
    // TODO: no error checking on success so silent failure if directory non-writable
    // beware: File is closed in outputRecords function
    $recAttr = array();

    if($intofile){
        $hunifile = fopen( HEURIST_HML_DIR.$record['rec_ID'].".xml", 'w');
        output( "<?xml version='1.0' encoding='UTF-8'?>\n" );

        //add attributes
        $recAttr['xmlns'] = 'http://heuristnetwork.org';
        $recAttr['xmlns:xsi'] = 'http://www.w3.org/2001/XMLSchema-instance';
        $recAttr['xsi:schemaLocation'] = 'http://heuristnetwork.org reference/scheme_record.xsd';
    }

        $recAttr['depth'] = $depth;
        $recAttr['visibility'] = ($record['rec_NonOwnerVisibility'] ? $record['rec_NonOwnerVisibility'] : 'viewable');
        $recAttr['visnote'] = ($record['rec_NonOwnerVisibility']=='hidden' ? 'owner group only'
                 : (($record['rec_NonOwnerVisibility']=='public') ? 'no login required' : 'logged in users') );
        $recAttr['selected'] = (in_array($record['rec_ID'], $selectedIDs) ? 'yes' : 'no');

    openTag('record', $recAttr);

    if (isset($dbID) && ($dbID != 0)) {
        output( "<dbID>".$dbID."</dbID>\n");
    } else {
        output( "<dbID>0</dbID>\n"); // unregistered database
    }

    if (array_key_exists('error', $record)) {
        makeTag('error', null, $record['error']);
        closeTag('record');
        return false;
    }

    if ($depth > $USEXINCLUDELEVEL) {
        outputXInclude($record);
        closeTag('record');
        return $record['rec_RecTypeID'];
    }

    $conceptID = getRecTypeConceptID($record['rec_RecTypeID']);

    makeTag('id', null, $record['rec_ID']);
    makeTag('type', array('id' => $record['rec_RecTypeID'],
        'conceptID' => $conceptID), $RTN[$record['rec_RecTypeID']]);
    makeTag('title', null, $record['rec_Title']);

    if ($record['rec_URL']) {
        makeTag('url', null, $record['rec_URL']);
    }

    if ($record['rec_ScratchPad']) {
        makeTag('notes', null, replaceIllegalChars($record['rec_ScratchPad']));
    }

    makeTag('added', null, $record['rec_Added']);
    makeTag('modified', null, $record['rec_Modified']);

    // saw FIXME  - need to output groups only
    if (array_key_exists($record['rec_OwnerUGrpID'], $WGN) || array_key_exists($record['rec_OwnerUGrpID'], $UGN)) {
        makeTag('workgroup', array('id' => $record['rec_OwnerUGrpID']), $record['rec_OwnerUGrpID'] > 0 ? (array_key_exists($record['rec_OwnerUGrpID'], $WGN) ? $WGN[$record['rec_OwnerUGrpID']] : (array_key_exists($record['rec_OwnerUGrpID'], $UGN) ? $UGN[$record['rec_OwnerUGrpID']] : 'Unknown')) : 'public');
    }

    foreach ($record['details'] as $dt => $details) {
        foreach ($details as $value) {
            outputDetail($dt, $value, $record['rec_RecTypeID'], $recInfos, $depth, $outputStub, $record['rec_RecTypeID'] == $relRT ? $parentID : $record['rec_ID']);
        }

    }
    if ($WOOT) {
        $result = loadWoot(array('title' => 'record:' . $record['rec_ID']));
        if ($result['success'] && is_numeric($result['woot']['id']) && count($result['woot']['chunks']) > 0) {
            openTag('woot', array('title' => 'record:' . $record['rec_ID']));
            openCDATA();
            foreach ($result['woot']['chunks'] as $chunk) {
                $text = preg_replace("/&nbsp;/", " ", $chunk['text']);
                output( replaceIllegalChars($text) . "\n" );
            }
            closeCDATA();
            closeTag('woot');
        }
    }

    if (array_key_exists('revPtrLinks', $recordInfo) && $recordInfo['revPtrLinks']['byRecIDs']) {
        foreach ($recordInfo['revPtrLinks']['byRecIDs'] as $rec_id => $dtIDs) {
            foreach ($dtIDs as $dtID) {
                $linkedRec = loadRecord_NoCache($rec_id, true); //$recInfos[$rec_id]['record'];
                $attrs = array('id' => $dtID, 'conceptID' => getDetailTypeConceptID($dtID), 'type' => $DTN[$dtID]);
                if (array_key_exists($dtID, $RQS[$linkedRec['rec_RecTypeID']])) {
                    $attrs['name'] = $RQS[$linkedRec['rec_RecTypeID']][$dtID];
                }
                makeTag('reversePointer', $attrs, $rec_id);
            }
        }
    }

    if (array_key_exists('revRelLinks', $recordInfo) && $recordInfo['revRelLinks']['relRecIDs']) {
        $recID = $record['rec_ID'];
        foreach ($recordInfo['revRelLinks']['relRecIDs'] as $relRec_id) {
            $relRec = loadRecord_NoCache($relRec_id, true); //$recInfos[$relRec_id]['record'];
            $attrs = array();
            if ($details = $relRec['details']) {
                if ($details[$relTrgDT]) {
                    list($key, $value) = each($details[$relTrgDT]);
                    $toRecord = $value;
                    if (intval($toRecord['id']) != $recID) {
                        $relatedRecID = $toRecord['id'];
                    } else {
                        $attrs['useInverse'] = 'true';
                        if ($details[$relSrcDT]) {
                            list($key, $value) = each($details[$relSrcDT]);
                            $fromRecord = $value;
                            if (intval($fromRecord['id']) != $recID) {
                                $relatedRecID = $fromRecord['id'];
                            }
                        }
                    }
                }

                if ($details[$relTypDT]) {
                    list($key, $value) = each($details[$relTypDT]);
                    preg_replace("/-/", "", $value);
                    $trmID = $value;
                    if ($trmID) { //saw Enum change
                        $attrs['type'] = $TL[$trmID]['trm_Label'];
                        $attrs['termID'] = $trmID;
                        $attrs['termConceptID'] = getTermConceptID($trmID);
                        if ($TL[$trmID]['trm_Code']) {
                            $attrs['code'] = $TL[$trmID]['trm_Code'];
                        };
                        if ($relatedRecID) {
                            $attrs['relatedRecordID'] = $relatedRecID;
                        }
                        if (array_key_exists($trmID, $INV) && $INV[$trmID]) {
                            $attrs['inverse'] = $TL[$INV[$trmID]]['trm_Label'];
                            $attrs['invTermID'] = $INV[$trmID];
                            $attrs['invTermConceptID'] = getTermConceptID($INV[$trmID]);
                        }
                    }
                }
            }
            makeTag('relationship', $attrs, $relRec_id);
        }
    }

    if (array_key_exists('relLinks', $recordInfo) && $recordInfo['relLinks']['relRecIDs']) {
        $recID = $record['rec_ID'];
        foreach ($recordInfo['relLinks']['relRecIDs'] as $relRec_id) {
            $relRec = loadRecord_NoCache($relRec_id, true); //$recInfos[$relRec_id]['record'];
            $attrs = array();
            if ($details = $relRec['details']) {
                if ($details[$relTrgDT]) {
                    list($key, $value) = each($details[$relTrgDT]);
                    $toRecord = $value;
                    if (intval($toRecord['id']) != $recID) {
                        $relatedRecID = $toRecord['id'];
                    } else {
                        $attrs['useInverse'] = 'true';
                        if ($details[$relSrcDT]) {
                            list($key, $value) = each($details[$relSrcDT]);
                            $fromRecord = $value;
                            if (intval($fromRecord['id']) != $recID) {
                                $relatedRecID = $fromRecord['id'];
                            }
                        }
                    }
                }

                if ($details[$relTypDT]) {
                    list($key, $value) = each($details[$relTypDT]);
                    preg_replace("/-/", "", $value);
                    $trmID = $value;
                    if ($trmID) { //saw Enum change
                        $attrs['type'] = $TL[$trmID]['trm_Label'];
                        $attrs['termID'] = $trmID;
                        $attrs['termConceptID'] = getTermConceptID($trmID);
                        if ($relatedRecID) {
                            $attrs['relatedRecordID'] = $relatedRecID;
                        }
                        if (array_key_exists($trmID, $INV) && $INV[$trmID]) {
                            $attrs['inverse'] = $TL[$INV[$trmID]]['trm_Label'];
                            $attrs['invTermID'] = $INV[$trmID];
                            $attrs['invTermConceptID'] = getTermConceptID($INV[$trmID]);
                        }
                    }
                }
            }
            makeTag('relationship', $attrs, $relRec_id);
        }
    }

    closeTag('record');

    return $record['rec_RecTypeID']; //true;
} //outputRecord


function outputXInclude($record) {
    global $RTN;
    $recID = $record['rec_ID'];
    $outputFilename = "" . HEURIST_DBID . "-" . $recID . ".hml";
    openTag('xi:include', array('href' => "" . $outputFilename . "#xpointer(//hml/records/record[id=$recID]/*)"));
    openTag('xi:fallback');
    makeTag('id', null, $recID);
    $type = $record['rec_RecTypeID'];
    makeTag('type', array('id' => $type, 'conceptID' => getRecTypeConceptID($type)), $RTN[$type]);
    $title = $record['rec_Title'];
    makeTag('title', null, "Record Not Available");
    makeTag('notavailable', null, null);
    closeTag('xi:fallback');
    closeTag('xi:include');
}


function outputRecordStub($recordStub) {
    global $RTN;
    openTag('record', array('isStub' => 1));
    makeTag('id', null, array_key_exists('id', $recordStub) ? $recordStub['id'] : $recordStub['rec_ID']);
    $type = array_key_exists('type', $recordStub) ? $recordStub['type'] : $recordStub['rec_RecTypeID'];
    makeTag('type', array('id' => $type, 'conceptID' => getRecTypeConceptID($type)), $RTN[$type]);
    $title = array_key_exists('title', $recordStub) ? $recordStub['title'] : $recordStub['rec_Title'];
    makeTag('title', null, $title);
    closeTag('record');
}


function makeFileContentNode($file) {
    $filename = $file['URL'];
    if ($file['mimeType'] === "application/xml") { // && file_exists($filename)) {
        if ($file['origName'] !== "_remote") {
            $xml = simplexml_load_file($filename);
            if (!$xml) {
                makeTag('error', null, " Error while attemping to read $filename .");
                return;
            }
            $xml = $xml->asXML();
        } else {
            $xml = loadRemoteURLContent($filename);
            if (!$xml) {
                makeTag('error', null, " Error while attemping to read $filename .");
                return;
            }
        }

        //embedding so remove any xml element
        $content = preg_replace("/^\<\?xml[^\?]+\?\>/", "", $xml);

        //embedding so remove any DOCTYPE  TODO saw need to validate first then this is fine. also perhaps attribute with type and ID
        $content = preg_replace("/\<\!DOCTYPE[^\[]+\s*\[\s*(?:(?:\<--)?\s*\<[^\>]+\>\s*(?:--\>)?)*\s*\]\>/", "", $content, 1);
        if (preg_match("/\<\!DOCTYPE/", $content)) {
            $content = preg_replace("/\<\!DOCTYPE[^\>]+\>/", "", $content, 1);
        }

        $parser = xml_parser_create();
        $ret = xml_parse_into_struct($parser, $content, $vals, $index);
        if ($ret == 0) {
            makeTag('error', null, "Invalid XML - ".xml_error_string(xml_get_error_code($parser)));
            xml_parser_free($parser);
            return;
        }

        if ($content) {
            makeTag('content', array("type" => "xml"), $content, true, false);
        }
        return;
    }
}


function outputDetail($dt, $value, $rt, $recInfos, $depth = 0, $outputStub, $parentID) {

    global $DTN, $DTT, $TL, $RQS, $INV, $GEO_TYPES, $MAX_DEPTH, $INCLUDE_FILE_CONTENT, $SUPRESS_LOOPBACKS, $relTypDT;
    $attrs = array('id' => $dt, 'conceptID' => getDetailTypeConceptID($dt));

    if (array_key_exists($dt, $DTN)) {
        $attrs['type'] = $DTN[$dt];
    }

    if (array_key_exists($rt, $RQS) && array_key_exists($dt, $RQS[$rt])) {
        $attrs['name'] = $RQS[$rt][$dt];
    }

    if ($dt === $relTypDT && array_key_exists($value, $INV) && $INV[$value] && array_key_exists($INV[$value], $TL)) { //saw Enum change
        $attrs['inverse'] = $TL[$INV[$value]]['trm_Label'];
        $attrs['invTermConceptID'] = getTermConceptID($INV[$value]);
    }

    if (is_array($value)) {
        if (array_key_exists('id', $value)) {
            // record pointer
            $attrs['isRecordPointer'] = "true";
            if ($MAX_DEPTH == 0 && $outputStub) {
                openTag('detail', $attrs);

                $recinfo = loadRecord_NoCache($value['id'], true);
                outputRecordStub($recinfo); //$recInfos[$value['id']]['record']);
                closeTag('detail');
            } else {
                makeTag('detail', $attrs, $value['id']);
            }
        } else if (array_key_exists('file', $value)) {
            $file = $value['file'];
            if (@$_REQUEST['includeresources'] == '1' && @$_REQUEST['mode'] == '1') {
                $file = get_uploaded_file_info_internal($file['id'], false);
                if ($file['fullpath'] && file_exists($file['fullpath'])) {
                    //backup file inot backup/user folder
                    $folder = HEURIST_FILESTORE_DIR . "backup/" . get_user_username() . "/";
                    $path_parts = pathinfo($file['fullpath']);
                    $file['URL'] = $path_parts['basename'];
                    $filename_bk = $folder . $file['URL'];
                    copy($file['fullpath'], $filename_bk);
                    unset($file['thumbURL']);
                }
            }
            openTag('detail', $attrs);
            openTag('file');
            makeTag('id', null, $file['id']);
            makeTag('nonce', null, $file['nonce']);
            makeTag('origName', null, $file['origName']);
            if (@$file['mimeType']) {
                makeTag('mimeType', null, $file['mimeType']);
            }
            if (@$file['fileSize']) {
                makeTag('fileSize', array('units' => 'kB'), $file['fileSize']);
            }
            if (@$file['date']) {
                makeTag('date', null, $file['date']);
            }
            if (@$file['description']) {
                makeTag('description', null, $file['description']);
            }
            if (@$file['URL']) {
                makeTag('url', null, $file['URL']);
            }
            if (@$file['thumbURL']) {
                makeTag('thumbURL', null, $file['thumbURL']);
            }
            if ($INCLUDE_FILE_CONTENT !== false && $INCLUDE_FILE_CONTENT >= $depth) {
                makeFileContentNode($file);
            }
            closeTag('file');
            closeTag('detail');
        } else if (array_key_exists('geo', $value)) {
            openTag('detail', $attrs);
            openTag('geo');
            makeTag('type', null, $GEO_TYPES[$value['geo']['type']]);
            makeTag('wkt', null, $value['geo']['wkt']);
            closeTag('geo');
            closeTag('detail');
        }
    } else if ($DTT[$dt] === 'date') {
        openTag('detail', $attrs);
        if (strpos($value, "|") === false) {
            outputDateDetail($attrs, $value);
        } else {
            outputTemporalDetail($attrs, $value);
        }
        closeTag('detail');
    } else if ($DTT[$dt] === 'resource') {
        $attrs['isRecordPointer'] = "true";
        if ($MAX_DEPTH == 0 && $outputStub) {
            openTag('detail', $attrs);
            $recinfo = loadRecord_NoCache($value['id'], true);
            outputRecordStub($recinfo); //$recInfos[$value['id']]['record']);
            closeTag('detail');
        } else {
            makeTag('detail', $attrs, $value['id']);
        }
    } else if (($DTT[$dt] === 'enum' || $DTT[$dt] === 'relationtype') && array_key_exists($value, $TL)) {
        $attrs['termID'] = $value;
        $attrs['termConceptID'] = getTermConceptID($value);
        if (@$TL[$value]['trm_ParentTermID']) {
            $attrs['ParentTerm'] = $TL[$TL[$value]['trm_ParentTermID']]['trm_Label'];
        }
        makeTag('detail', $attrs, $TL[$value]['trm_Label']);
    } else {
        makeTag('detail', $attrs, replaceIllegalChars($value));
    }
}

$typeDict = array("s" => "Simple Date", "c" => "C14 Date", "f" => "Aproximate Date", "p" => "Date Range", "d" => "Duration");
$fieldsDict = array("VER" => "Version Number", "TYP" => "Temporal Type Code", "PRF" => "Probability Profile", "SPF" => "Start Profile", "EPF" => "End Profile", "CAL" => "Calibrated", "COD" => "Laboratory Code", "DET" => "Determination Type", "COM" => "Comment", "EGP" => "Egyptian Date");
$determinationCodes = array(0 => "Unknown", 1 => "Attested", 2 => "Conjecture", 3 => "Measurement");
$profileCodes = array(0 => "Flat", 1 => "Central", 2 => "Slow Start", 3 => "Slow Finish");
$tDateDict = Array("DAT" => "ISO DateTime", "BPD" => "Before Present (1950) Date", "BCE" => "Before Current Era", "TPQ" => "Terminus Post Quem", "TAQ" => "Terminus Ante Quem", "PDB" => "Probable begin", "PDE" => "Probable end", "SRT" => "Sortby Date");
$tDurationDict = array("DUR" => "Simple Duration", "RNG" => "Range", "DEV" => "Standard Deviation", "DVP" => "Deviation Positive", "DVN" => "Deviation Negative", "ERR" => "Error Margin");



function outputTemporalDetail($attrs, $value) {
    global $typeDict, $fieldsDict, $determinationCodes, $profileCodes, $tDateDict, $tDurationDict;
    $temporalStr = substr_replace($value, "", 0, 1); // remove first verticle bar
    $props = explode("|", $temporalStr);
    $properties = array();
    foreach ($props as $prop) {
        list($tag, $val) = explode("=", $prop);
        $properties[$tag] = $val;
    }
    openTag('temporal', array("version" => $properties['VER'], "type" => $typeDict[$properties['TYP']]));
    unset($properties['VER']);
    unset($properties['TYP']);
    foreach ($properties as $tag => $val) {
        if (array_key_exists($tag, $fieldsDict)) { //simple property
            openTag('property', array('type' => $tag, 'name' => $fieldsDict[$tag]));
            switch ($tag) {
                case "DET":
                    output( $determinationCodes[$val] );
                    break;
                case "PRF":
                case "SPF":
                case "EPF":
                    output( $profileCodes[$val] );
                    break;
                default:
                    output( $val );
            }
            closeTag('property');
        } else if (array_key_exists($tag, $tDateDict)) {
            openTag('date', array('type' => $tag, 'name' => $tDateDict[$tag]));
            outputTDateDetail(null, $val);
            closeTag('date');
        } else if (array_key_exists($tag, $tDurationDict)) {
            openTag('duration', array('type' => $tag, 'name' => $tDurationDict[$tag]));
            outputDurationDetail(null, $val);
            closeTag('duration');
        }
    }
    closeTag('temporal');
}


function outputDateDetail($attrs, $value) {
    makeTag('raw', null, $value);
    if (preg_match('/^\\s*-?(\\d+)\\s*$/', $value, $matches)) { // year only
        makeTag('year', null, $matches[1]);
    } else {
        $date = strtotime($value);
        if ($date) {
            makeTag('year', null, date('Y', $date));
            makeTag('month', null, date('n', $date));
            makeTag('day', null, date('j', $date));
            if (preg_match("![ T]!", $value)) { // looks like there's a time
                makeTag('hour', null, date('H', $date));
                makeTag('minutes', null, date('i', $date));
                makeTag('seconds', null, date('s', $date));
            }
        } else {
            // cases that strtotime doesn't catch
            if (preg_match('/^([+-]?\d\d\d\d+)-(\d\d)$/', $value, $matches)) {
                // e.g. MMMM-DD
                makeTag('year', null, intval($matches[1]));
                makeTag('month', null, intval($matches[2]));
            } else {
                @list($date, $time) = preg_split("![ T]!", $value);
                @list($y, $m, $d) = array_map("intval", preg_split("![-\/]!", $date));
                if (!(1 <= $m && $m <= 12 && 1 <= $d && $d <= 31)) {
                    @list($d, $m, $y) = array_map("intval", preg_split("![-\/]!", $date));
                }
                if (!(1 <= $m && $m <= 12 && 1 <= $d && $d <= 31)) {
                    @list($m, $d, $y) = array_map("intval", preg_split("![-\/]!", $date));
                }
                if (1 <= $m && $m <= 12 && 1 <= $d && $d <= 31) {
                    makeTag('year', null, $y);
                    makeTag('month', null, $m);
                    makeTag('day', null, $d);
                }
                @list($h, $m, $s) = array_map("intval", preg_split("![-:]!", $time));
                if (0 <= $h && $h <= 23) {
                    makeTag('hour', null, $h);
                }
                if (0 <= $m && $m <= 59) {
                    makeTag('minutes', null, $m);
                }
                if (0 <= $s && $s <= 59) {
                    makeTag('seconds', null, $s);
                }
            }
        }
    }
}



function outputTDateDetail($attrs, $value) {
    makeTag('raw', null, $value);
    if (preg_match('/^([^T\s]*)(?:[T\s+](\S*))?$/', $value, $matches)) { // valid ISO Duration split into date and time
        $date = @$matches[1];
        $time = @$matches[2];
        if (!$time && preg_match('/:\./', $date)) {
            $time = $date;
            $date = null;
        }
        if ($date) {
            preg_match('/^(?:(\d\d\d\d)[-\/]?)?(?:(1[012]|0[23]|[23](?!\d)|0?1(?!\d)|0?[4-9](?!\d))[-\/]?)?(?:([12]\d|3[01]|0?[1-9]))?\s*$/', $date, $matches);
            if (@$matches[1]) makeTag('year', null, $matches[1]);
            if (@$matches[2]) makeTag('month', null, $matches[2]);
            if (@$matches[3]) makeTag('day', null, $matches[3]);
        }
        if ($time) {
            preg_match('/(?:(1\d|0?[1-9]|2[0-3]))?(?:[:\.](?:(0[0-9]|[0-5]\d)))?(?:[:\.](?:(0[0-9]|[0-5]\d)))?/', $time, $matches);
            if (@$matches[1]) makeTag('hour', null, $matches[1]);
            if (@$matches[2]) makeTag('minutes', null, $matches[2]);
            if (@$matches[3]) makeTag('seconds', null, $matches[3]);
        }
    }
}



function outputDurationDetail($attrs, $value) {
    makeTag('raw', null, $value);
    if (preg_match('/^P([^T]*)T?(.*)$/', $value, $matches)) { // valid ISO Duration split into date and time
        $date = @$matches[1];
        $time = @$matches[2];
        if ($date) {
            if (preg_match('/[YMD]/', $date)) { //char separated version 6Y5M8D
                preg_match('/(?:(\d+)Y)?(?:(\d|0\d|1[012])M)?(?:(0?[1-9]|[12]\d|3[01])D)?/', $date, $matches);
            } else { //delimited version  0004-12-06
                preg_match('/^(?:(\d\d\d\d)[-\/]?)?(?:(1[012]|0[23]|[23](?!\d)|0?1(?!\d)|0?[4-9](?!\d))[-\/]?)?(?:([12]\d|3[01]|0?[1-9]))?\s*$/', $date, $matches);
            }
            if (@$matches[1]) makeTag('year', null, intval($matches[1]));
            if (@$matches[2]) makeTag('month', null, intval($matches[2]));
            if (@$matches[3]) makeTag('day', null, intval($matches[3]));
        }
        if ($time) {
            if (preg_match('/[HMS]/', $time)) { //char separated version 6H5M8S
                preg_match('/(?:(0?[1-9]|1\d|2[0-3])H)?(?:(0?[1-9]|[0-5]\d)M)?(?:(0?[1-9]|[0-5]\d)S)?/', $time, $matches);
            } else { //delimited version  23:59:59
                preg_match('/(?:(0?[1-9]|1\d|2[0-3])[:\.])?(?:(0?[1-9]|[0-5]\d)[:\.])?(?:(0?[1-9]|[0-5]\d))?/', $time, $matches);
            }
            if (@$matches[1]) makeTag('hour', null, intval($matches[1]));
            if (@$matches[2]) makeTag('minutes', null, intval($matches[2]));
            if (@$matches[3]) makeTag('seconds', null, intval($matches[3]));
        }
    }
}

$invalidChars = array(chr(0), chr(1), chr(2), chr(3), chr(4), chr(5), chr(6), chr(7), chr(8), chr(11), chr(12), chr(14), chr(15), chr(16), chr(17), chr(18), chr(19), chr(20), chr(21), chr(22), chr(23), chr(24), chr(25), chr(26), chr(27), chr(28), chr(29), chr(30), chr(31)); // invalid chars that need to be stripped from the data.
$replacements = array("[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", " ", "[?]", "[?]", "[?]", "[?]", "[?]");



function replaceIllegalChars($text) {
    global $invalidChars, $replacements;
    return str_replace($invalidChars, $replacements, $text);
}

function check($text) {
    global $invalidChars;
    foreach ($invalidChars as $charCode) {
        //$pattern = "". chr($charCode);
        if (strpos($text, $charCode)) {
            return false;
        }
    }
    return true;
}

//----------------------------------------------------------------------------//
//  Turn off output buffering
//----------------------------------------------------------------------------//

if (!@$ARGV) {
    @apache_setenv('no-gzip', 1);
}
if (@$_REQUEST['mode'] != '1') { //not include
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    for ($i = 0;$i < ob_get_level();$i++) {
        ob_end_flush();
    }
    ob_implicit_flush(1);
}

//----------------------------------------------------------------------------//
//  Output
//----------------------------------------------------------------------------//

$hmlAttrs = array();

    $hmlAttrs['xmlns'] = 'http://heuristnetwork.org';
    $hmlAttrs['xmlns:xsi'] = 'http://www.w3.org/2001/XMLSchema-instance';
    $hmlAttrs['xsi:schemaLocation'] = 'http://heuristnetwork.org reference/scheme_hml.xsd';

if ($USEXINCLUDE) {
    $hmlAttrs['xmlns:xi'] = 'http://www.w3.org/2001/XInclude';
}

if (@$_REQUEST['filename']) {
    $hmlAttrs['filename'] = $_REQUEST['filename'];
}

if (@$_REQUEST['pathfilename']) {
    $hmlAttrs['pathfilename'] = $_REQUEST['pathfilename'];
}

if(true || @$_REQUEST['rules']){ //search with h4 search engine

    $url = HEURIST_BASE_URL."hserver/controller/record_search.php";

    $url = $url.'?'.$_SERVER["QUERY_STRING"]."&detail=ids&vo=h3&needall=1";    //call h4
    if($PUBONLY){
        $url = $url."&publiconly=1";
    }

    /*
    $result = loadRemoteURLContent($url);
    $result = json_decode($result, true);
    */

    $hostname = '127.0.0.1'; //HEURIST_DOMAIN;
    $url = str_replace(HEURIST_DOMAIN, $hostname, $url);

    $headers[] = "GET ".$url." HTTP/1.1";
    $headers[] = "Host: ".$hostname;
    $headers[] = "Accept-language: en";

    $cookies = array();
    foreach ($_COOKIE as $id=>$val){
        array_push($cookies, $id.'='.$val );
    }
    $headers[] = "Cookie: ".implode($cookies,';').';';
    $headers[] = "";

    $CRLF = "\r\n";
    $remote = fsockopen($hostname, 80, $errno, $errstr, 5);
    if($remote===false){
       $result = array();
    }else{
        // a pinch of error handling here
        fwrite($remote, implode($CRLF, $headers).$CRLF);
        $response = '';
        while ( ! feof($remote))
        {
            // Get 100K from buffer
            $response .= fread($remote, 102400);
        }
        fclose($remote);


        // split the headers and the body
        $response2 = preg_split("|(?:\r?\n){2}|m", $response, 2);
        $response2 = (isset($response2[1]))?$response2[1]:"";

        if(strpos($response2,$CRLF)>0){
            $response2 = explode($CRLF, $response2);
            $response2 = $response2[1];
        }

        $result = json_decode($response2, true);
    }

    /* it requires pecl http
    $r = new HttpRequest($url, HttpRequest::METH_GET);
    $r->addCookies($_COOKIE);
    $r->addQueryData($_REQUEST);
    try {
        $result = $r->getResponseBody(); //send()->getBody();
        $result = json_decode($result, true);
    } catch (HttpException $ex) {
        echo $ex;
        exit();
    }
    */


}else{
    $result = loadSearch($_REQUEST, false, true, $PUBONLY); //load IDS only
}

$query_attrs = array_intersect_key($_REQUEST, array('q' => 1, 'w' => 1, 'pubonly' => 1, 'hinclude' => 1, 'depth' => 1, 'sid' => 1, 'label' => 1, 'f' => 1, 'limit' => 1, 'offset' => 1, 'db' => 1, 'expandColl' => 1, 'recID' => 1, 'stub' => 1, 'woot' => 1, 'fc' => 1, 'slb' => 1, 'fc' => 1, 'slb' => 1, 'selids' => 1, 'layout' => 1, 'rtfilters' => 1, 'relfilters' => 1, 'ptrfilters' => 1));

/*if(@$_REQUEST['offset']){
$offset = intval($_REQUEST['offset']);
}else{
$offset = 0;
}
$last_limit = null;
if(@$_REQUEST['limit']){
$limit = intval($_REQUEST['limit']);
if($limit>1000){
$last_limit = $limit+$offset;
$limit = 1000;
}
}*/

if($intofile){ // flags HuNI manifest + separate files per record

    if (array_key_exists('error', $result)) {
        print "Error: ".$result['error'];
    }else{

        // write out all records as separate files
        $resout = outputRecords($result);

        // create HuNI manifest
        $huni_resources = fopen( HEURIST_HML_DIR."resources.xml","w");
        fwrite( $huni_resources, "<?xml version='1.0' encoding='UTF-8'?>\n" );
        fwrite( $huni_resources, '<resources recordCount="'.count($resout)."\">\n");

        // dbID set at start of script
        if (isset($dbID) && ($dbID != 0)) {
            fwrite( $huni_resources, "<dbID>".$dbID."</dbID>\n");
        }
        else
        {
            fwrite( $huni_resources, "<dbID>0</dbID>\n"); // unregistered indicated by 0
        }

        // add each output file to the manifest
        foreach ($resout as $recID => $recTypeID) {

            $resfile = HEURIST_HML_DIR.$recID.".xml";

            if(file_exists($resfile)){
                $conceptID = getRecTypeConceptID($recTypeID);
                fwrite( $huni_resources, "<record>\n");
                fwrite( $huni_resources, "<name>".$recID.".xml</name>\n");
                fwrite( $huni_resources, "<hash>".md5_file($resfile)."</hash>\n");
                fwrite( $huni_resources, "<RTConceptID>".$conceptID."</RTConceptID>\n");
                fwrite( $huni_resources, "</record>\n");
            }
        }

        fwrite( $huni_resources, "</resources>");
        fclose( $huni_resources );

        print "<h3>Export completed</h3> Harvestable file(s) are in <b>".HEURIST_HML_DIR."</b>";
    }
    /*

    $_REQUEST['limit'] = 1000;

    $total_out_count = 0;

    // create HuNI manifest
    $huni_resources = fopen( HEURIST_HML_DIR."resources.xml","w");
    fwrite( $huni_resources, "<?xml version='1.0' encoding='UTF-8'?>\n" );

    // dbID set at start of script
    if (isset($dbID) && ($dbID != 0)) {
    fwrite( $huni_resources, "<dbID>".$dbID."</dbID>\n");
    }
    else
    {
    fwrite( $huni_resources, "<dbID>0</dbID>\n"); // unregistered indicated by 0
    }


    while ($last_limit==null || $offset<$last_limit){

    $_REQUEST['offset'] = $offset;
    $result = loadSearch($_REQUEST, false, true, $PUBONLY);

    if (array_key_exists('error', $result)) {
    print "Error: ".$result['error'];
    break;
    }else if($result['recordCount']>0){

    // write out all records as separate files
    $resout = outputRecords($result);

    // add each output file to the manifest
    foreach ($resout as $recID => $recTypeID) {

    $resfile = HEURIST_HML_DIR.$recID.".xml";

    if(file_exists($resfile)){
    $conceptID = getRecTypeConceptID($recTypeID);
    fwrite( $huni_resources, "<record>\n");
    fwrite( $huni_resources, "<name>".$recID.".xml</name>\n");
    fwrite( $huni_resources, "<hash>".md5_file($resfile)."</hash>\n");
    fwrite( $huni_resources, "<RTConceptID>".$conceptID."</RTConceptID>\n");
    fwrite( $huni_resources, "</record>\n");
    }
    }

    $offset = $offset + 1000;
    $total_out_count = $total_out_count + count($resout);

    if($result['recordCount']<100){
    break;
    }
    }
    }

    fseek( $huni_resources, 1);   // move pointer to the file position where we saved above
    fwrite( $huni_resources, '<resources recordCount="'.count($total_out_count)."\">\n");      // !!!!!!

    fwrite($huni_resources, $newline);

    fwrite( $huni_resources, "</resources>");
    fclose( $huni_resources );

    print "<h3>Export completed</h3> Harvestable file(s) are in <b>".HEURIST_HML_DIR."</b>";
    */
}else{ // single output stream

    openTag('hml', $hmlAttrs);

    //makeTag('raw',null, $response2 );

    /*
    openTag('hml', array(
    'xmlns' => 'http://heuristscholar.org/heurist/hml',
    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
    'xsi:schemaLocation' => 'http://heuristscholar.org/heurist/hml http://heuristscholar.org/heurist/schemas/hml.xsd')
    );
    */
    makeTag('database', array('id' => HEURIST_DBID), HEURIST_DBNAME);
    makeTag('query', $query_attrs);
    if (count($selectedIDs) > 0) {
        makeTag('selectedIDs', null, join(",", $selectedIDs));
    }
    makeTag('dateStamp', null, date('c'));
    if (array_key_exists('error', $result)) {
        makeTag('error', null, $result['error']);
    } else {
        makeTag('resultCount', null, @$result['resultCount']>0 ? $result['resultCount'] : " 0 ");
        makeTag('recordCount', null, @$result['recordCount']>0 ? $result['recordCount'] : " 0 ");
        // Output all the records as XML blocks
        if (@$result['recordCount'] > 0) outputRecords($result);
    }
    closeTag('hml');
}
?>