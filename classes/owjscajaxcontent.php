<?php
//
// Definition of ezjscAjaxContent class
//
// Created on: <5-Aug-2007 00:00:00 ar>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ JSCore extension for eZ Publish
// SOFTWARE RELEASE: 1.x
// COPYRIGHT NOTICE: Copyright (C) 1999-2014 eZ Systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
// 
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
// 
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

// Simplifying and encoding content objects / nodes to json
// using the php json extension included in php 5.2


class owjscAjaxContent extends ezjscAjaxContent
{
    /**
     * Function for encoding content object(s) or node(s) to simplified
     * json objects, xml or array hash
     *
     * @param mixed $obj
     * @param array $params
     * @param string $type
     * @return mixed
     */
    public static function nodeEncode( $obj, $params = array(), $type = 'json' )
    {
        if ( is_array( $obj ) )
        {
            $ret = array();
            foreach ( $obj as $ob )
            {
                $ret[] = self::simplify( $ob, $params );
            }
        }
        else
        {
            $ret = self::simplify( $obj, $params );
        }

        if ( $type === 'xml' )
            return self::xmlEncode( $ret );
        else if ( $type === 'json' )
            return json_encode( $ret );
        else
            return $ret;
    }

    /**
     * Function for simplifying a content object or node
     *
     * @param mixed $obj
     * @param array $params
     * @return array
     */
    public static function simplify( $obj, $params = array() )
    {
        if ( !$obj )
        {
            return array();
        }
        else if ( $obj instanceof eZContentObject)
        {
            $node          = $obj->attribute( 'main_node' );
            $contentObject = $obj;
        }
        else if ( $obj instanceof eZContentObjectTreeNode || $obj instanceof eZFindResultNode )
        {
            $node          = $obj;
            $contentObject = $obj->attribute( 'object' );
        }
        else if( isset( $params['fetchNodeFunction'] ) && method_exists( $obj, $params['fetchNodeFunction'] ) )
        {
            // You can supply fetchNodeFunction parameter to be able to support other node related classes
            $node = call_user_func( array( $obj, $params['fetchNodeFunction'] ) );
            if ( !$node instanceof eZContentObjectTreeNode )
            {
                return '';
            }
            $contentObject = $node->attribute( 'object' );
        }
        else if ( is_array( $obj ) )
        {
            return $obj; // Array is returned as is
        }
        else
        {
            return ''; // Other passed objects are not supported
        }

        $ini = eZINI::instance( 'site.ini' );
        $params = array_merge( array(
                            'dataMap' => array(), // collection of identifiers you want to load, load all with array('all')
                            'fetchPath' => false, // fetch node path
                            'fetchSection' => false, // fetch section
                            'fetchChildrenCount' => false,
                            'dataMapType' => array(), //if you want to filter datamap by type
                            'loadImages' => false,
                            'imagePreGenerateSizes' => array('small') //Pre generated images, loading all can be quite time consuming
        ), $params );

        if ( !isset( $params['imageSizes'] ) )// list of available image sizes
        {
            $imageIni = eZINI::instance( 'image.ini' );
            $params['imageSizes'] = $imageIni->variable( 'AliasSettings', 'AliasList' );
        }

        if ( $params['imageSizes'] === null || !isset( $params['imageSizes'][0] ) )
            $params['imageSizes'] = array();

        if (  !isset( $params['imageDataTypes'] ) )
            $params['imageDataTypes'] = $ini->variable( 'ImageDataTypeSettings', 'AvailableImageDataTypes' );

        $ret                            	= array();
        $attrtibuteArray                	= array();
        $ret['name']                    	= htmlentities( $contentObject->attribute( 'name' ), ENT_QUOTES, "UTF-8" );
        $ret['contentobject_id']        	= $ret['id'] = (int) $contentObject->attribute( 'id' );
        $ret['contentobject_remote_id'] 	= $contentObject->attribute( 'remote_id' );
        $ret['contentobject_state']     	= implode( ", ", $contentObject->attribute( 'state_identifier_array' ) );
        $ret['main_node_id']            	= (int)$contentObject->attribute( 'main_node_id' );
        $ret['version']                 	= (int)$contentObject->attribute( 'current_version' );
        $ret['modified']                	= $contentObject->attribute( 'modified' );
        $ret['published']               	= $contentObject->attribute( 'published' );
        $ret['section_id']              	= (int) $contentObject->attribute( 'section_id' );
        $ret['current_language']        	= $contentObject->attribute( 'current_language' );
        $ret['owner_id']                	= (int) $contentObject->attribute( 'owner_id' );
        $ret['class_id']                	= (int) $contentObject->attribute( 'contentclass_id' );
        $ret['class_name']              	= $contentObject->attribute( 'class_name' );
        $ret['path_identification_string'] 	= $node ? $node->attribute( 'path_identification_string' ) : '';
        $ret['translations']            	= eZContentLanguage::decodeLanguageMask($contentObject->attribute( 'language_mask' ), true);
        $ret['can_edit']                	= $contentObject->attribute( 'can_edit' );

        if ( isset( $params['formatDate'] ) )
        {
            $ret['modified_date'] = self::formatLocaleDate( $contentObject->attribute( 'modified' ), $params['formatDate'] );
            $ret['published_date'] = self::formatLocaleDate( $contentObject->attribute( 'published' ), $params['formatDate'] );
        }

        if ( isset( $params['fetchCreator'] ) )
        {
            $creator = $contentObject->attribute( 'current' )->attribute('creator');
            if ( $creator instanceof eZContentObject )
            {
                $ret['creator'] = array( 'id'   => $creator->attribute( 'id' ),
                                         'name' => $creator->attribute('name') );
            }
            else
            {
                $ret['creator'] = array( 'id'   => $contentObject->attribute( 'current' )->attribute('creator_id'),
                                         'name' => null );// user has been deleted
            }
        }

        if ( isset( $params['fetchClassIcon'] ) )
        {
            $operator = new eZWordToImageOperator();
            $tpl = eZTemplate::instance();

            $operatorValue = $contentObject->attribute( 'class_identifier' );

            $operatorParameters = array( array( array( 1, 'small' ) ) );
            $namedParameters = array();

            $operatorName = 'class_icon';

            $operator->modify(
                $tpl, $operatorName, $operatorParameters, '', '',
                $operatorValue, $namedParameters, array()
            );

            $ret['class_icon'] = $operatorValue;
        }

        if ( isset( $params['fetchThumbPreview'] ) )
        {
            $thumbUrl = '';
            $thumbWidth = 0;
            $thumbHeight = 0;
            $thumbDataType = isset( $params['thumbDataType'] ) ? $params['thumbDataType'] : 'ezimage';
            $thumbImageSize = isset( $params['thumbImageSize'] ) ? $params['thumbImageSize'] : 'small';

            foreach( $contentObject->attribute( 'data_map' ) as $key => $atr )
            {
                if ( $atr->attribute( 'data_type_string' ) == $thumbDataType
                        && $atr->attribute( 'has_content' ) )
                {
                    $imageContent = $atr->attribute( 'content' );

                    if ( $imageContent->hasAttribute( $thumbImageSize ) )
                        $imageAlias = $imageContent->attribute( $thumbImageSize );
                    else
                        eZDebug::writeError( "Image alias does not exist: '{$thumbImageSize}', missing from image.ini?",
                            __METHOD__ );

                    $thumbUrl = isset( $imageAlias['full_path'] ) ? $imageAlias['full_path'] : '';
                    $thumbWidth = isset( $imageAlias['width'] ) ? (int) $imageAlias['width'] : 0;
                    $thumbHeight = isset( $imageAlias['height'] ) ? (int) $imageAlias['height'] : 0;

                    if ( $thumbUrl !== '' )
                        eZURI::transformURI( $thumbUrl, true );

                    break;
                }
            }

            $ret['thumbnail_url'] = $thumbUrl;
            $ret['thumbnail_width'] = $thumbWidth;
            $ret['thumbnail_height'] = $thumbHeight;
        }

        if ( $params['fetchSection'] )
        {
            $section = eZSection::fetch( $ret['section_id']  );
            if ( $section instanceof eZSection )
            {
                $ret['section'] = array(
                    'id'                         => $section->attribute('id'),
                    'name'                       => $section->attribute('name'),
                    'navigation_part_identifier' => $section->attribute('navigation_part_identifier'),
                    'locale'                     => $section->attribute('locale'),
                );
            }
            else
            {
                $ret['section'] = null;
            }
        }

        if ( $node )
        {
            // optimization for eZ Publish 4.1 (avoid fetching class)
            if ( $node->hasAttribute( 'is_container' ) )
            {
                $ret['class_identifier'] = $node->attribute( 'class_identifier' );
                $ret['is_container']     = (int) $node->attribute( 'is_container' );
            }
            else
            {
                $class                   = $contentObject->attribute( 'content_class' );
                $ret['class_identifier'] = $class->attribute( 'identifier' );
                $ret['is_container']     = (int) $class->attribute( 'is_container' );
            }

            $ret['node_id']              = (int) $node->attribute( 'node_id' );
            $ret['parent_node_id']       = (int) $node->attribute( 'parent_node_id' );
            $ret['node_remote_id']       = $node->attribute( 'remote_id' );
            $ret['url_alias']            = $node->attribute( 'url_alias' );
            $ret['url']                  = $node->url();
            // force system url on empty urls (root node)
            if ( $ret['url'] === '' )
                $ret['url'] = 'content/view/full/' . $node->attribute( 'node_id' );
            eZURI::transformURI( $ret['url'] );

            $ret['depth']                = (int) $node->attribute( 'depth' );
            $ret['priority']             = (int) $node->attribute( 'priority' );
            $ret['hidden_status_string'] = $node->attribute( 'hidden_status_string' );

            if ( $params['fetchPath'] )
            {
                $ret['path'] = array();
                foreach ( $node->attribute( 'path' ) as $n )
                {
                    $ret['path'][] = self::simplify( $n );
                }
            }
            else
            {
                $ret['path'] = false;
            }

            if ( $params['fetchChildrenCount'] )
            {
                $ret['children_count'] = $ret['is_container'] ? (int) $node->attribute( 'children_count' ) : 0;
            }
            else
            {
                $ret['children_count'] = false;
            }
        }
        else
        {
            $class                   = $contentObject->attribute( 'content_class' );
            $ret['class_identifier'] = $class->attribute( 'identifier' );
            $ret['is_container']     = (int) $class->attribute( 'is_container' );
        }

        $ret['image_attributes'] = array();

        if ( is_array( $params['dataMap'] ) && is_array(  $params['dataMapType'] ) )
        {
            $dataMap = $contentObject->attribute( 'data_map' );
            $datatypeBlacklist = array_fill_keys(
                $ini->variable( 'ContentSettings', 'DatatypeBlackListForExternal' ),
                true
            );
            foreach( $dataMap as $key => $atr )
            {
                $dataTypeString = $atr->attribute( 'data_type_string' );
                //if ( in_array( $dataTypeString, $params['imageDataTypes'], true) !== false )

                if ( !in_array( 'all' ,$params['dataMap'], true )
                   && !in_array( $key ,$params['dataMap'], true )
                   && !in_array( $dataTypeString, $params['dataMapType'], true )
                   && !( $params['loadImages'] && in_array( $dataTypeString, $params['imageDataTypes'], true ) ) )
                {
                    continue;
                }

                $attrtibuteArray[ $key ]['id']         = $atr->attribute( 'id' );
                $attrtibuteArray[ $key ]['type']       = $dataTypeString;
                $attrtibuteArray[ $key ]['identifier'] = $key;
                if ( isset ( $datatypeBlacklist[$dataTypeString] ) )
                    $attrtibuteArray[ $key ]['content'] = null;
                else
                    $attrtibuteArray[ $key ]['content'] = $atr->toString();

                // images
                if ( in_array( $dataTypeString, $params['imageDataTypes'], true) && $atr->hasContent() )
                {
                    $content    = $atr->attribute( 'content' );
                    $imageArray = array();
                    if ( $content != null )
                    {
                        foreach( $params['imageSizes'] as $size )
                        {
                            $imageArray[ $size ] = false;
                            if ( in_array( $size, $params['imagePreGenerateSizes'], true ) )
                            {
                                if ( $content->hasAttribute( $size ) )
                                    $imageArray[ $size ] = $content->attribute( $size );
                                else
                                    eZDebug::writeError( "Image alias does not exist: '$size', missing from image.ini?",
                                        __METHOD__ );
                            }
                        }
                        $ret['image_attributes'][] = $key;
                    }

                    if ( !isset( $imageArray['original'] ) )
                        $imageArray['original'] = $content->attribute( 'original' );

                    array_walk_recursive(
                        $imageArray,
                        function ( &$element, $key )
                        {
                            // These fields can contain non utf-8 content
                            // badly handled by mb_check_encoding
                            // so they are just encoded in base64
                            // see https://jira.ez.no/browse/EZP-21358
                            if ( $key == "MakerNote" || $key == "UserComment")
                            {
                                $element =  base64_encode( (string)$element );
                            }
                            // json_encode/xmlEncode need UTF8 encoded strings
                            // (exif) metadata might not be for instance
                            // see https://jira.ez.no/browse/EZP-19929
                            else if ( !mb_check_encoding( $element, 'UTF-8' ) )
                            {
                                $element = mb_convert_encoding(
                                    (string)$element, 'UTF-8'
                                );
                            }
                        }
                    );

                    $attrtibuteArray[ $key ]['content'] = $imageArray;
                }
            }
        }
        $ret[$ret['class_identifier']]['data_map'] = $attrtibuteArray;
        return $ret;
    }

}

?>