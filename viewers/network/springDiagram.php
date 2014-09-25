<?php
    /**
    * sprintDiagram.php: Renders multiple levels of search results 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    require_once (dirname(__FILE__) . '/../../common/connect/applyCredentials.php');
    require_once (dirname(__FILE__) . '/../../common/php/getRecordInfoLibrary.php');

    //mysql_connection_select(DATABASE);
?>

<html>

    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Spring Diagram</title>

        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">    
        <link rel="stylesheet" type="text/css" href="../../common/css/diagram.css">

         <!-- jQuery -->
        <script type="text/javascript" src="../../external/jquery/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
        
        <!-- D3 -->
        <script type="text/javascript" src="../../external/d3/d3.js"></script>
        <script type="text/javascript" src="../../external/d3/fisheye.js"></script>
        
        <!-- Colpick -->
        <script type="text/javascript" src="../../external/colpick/colpick.js"></script>
        <link rel="stylesheet" type="text/css" href="../../external/colpick/colpick.css">
        
        <!-- Visualize plugin --> 
        <script type="text/javascript" src="../../common/js/visualize.js"></script>
        <link rel="stylesheet" type="text/css" href="../../common/css/visualize.css">                            
    </head>
    
    <body>
        <!-- Toolbar -->
        <div id="toolbardiv" style="width:100%;height:30px;">
            <ul id="diagram_list" class="horizontal menu">
                <li>
                    <span id="menuButton" class="button">Menu</span>
                    <ul id="toolMenuItems">
                        <li class="yuimenuitemlabel"><a href="#" onClick="getDiagramUrl();">Get URL for diagram</a></li>
                        <li class="yuimenuitemlabel"><a href="#" onClick="getDiagramCode();">Embed diagram code</a></li>
                    </ul>
                </li>
            </ul>
        </div>

        <!-- Visualize HTML -->
        <?php include "../../common/html/visualize.html"; ?>
        
        <!-- Script to parse data and create the visualisation -->
        <script type="text/javascript" src="springDiagram.js"></script>
    </body>
    
</html>