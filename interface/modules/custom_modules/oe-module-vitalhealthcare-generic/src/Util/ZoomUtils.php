<?php

namespace Vitalhealthcare\OpenEMR\Modules\Generic\Util;

use OpenEMR\Common\Crypto\CryptoGen;

class ZoomUtils
{
    public static function zoomGetUserCountOnWaitingRoom($m_id = '') {
        if(empty($m_id)) {
            return array();
        }

        $waiting_room_data = sqlQuery("select IF(w2.participant_joined_waiting_room_count > 0, w2.waiting_room_count, (w2.participant_jbh_waiting_count - w2.participant_jbh_waiting_left_count)) as waiting_user_count, w2.* from (select (w1.participant_jbh_waiting_count - w1.participant_jbh_waiting_left_count) as jbh_waiting_count, (((w1.participant_joined_waiting_room_count - w1.participant_left_waiting_room_count)) - w1.participant_admitted_count ) as waiting_room_count, (w1.participant_joined_count - w1.participant_left_count) as in_meeting_count, w1.* from (select (select count(meeting_id) from vh_zoom_webhook_event vzwe where vzwe.event in ('meeting.participant_jbh_waiting') and vzwe.meeting_id = '".$m_id."') as participant_jbh_waiting_count, (select count(meeting_id) from vh_zoom_webhook_event vzwe where vzwe.event in ('meeting.participant_jbh_waiting_left') and vzwe.meeting_id = '".$m_id."') as participant_jbh_waiting_left_count, (select count(meeting_id) from vh_zoom_webhook_event vzwe where vzwe.event in ('meeting.participant_joined_waiting_room') and vzwe.meeting_id = '".$m_id."') as participant_joined_waiting_room_count, (select count(meeting_id) from vh_zoom_webhook_event vzwe where vzwe.event in ('meeting.participant_left_waiting_room') and vzwe.meeting_id = '".$m_id."') as participant_left_waiting_room_count, (select count(meeting_id) from vh_zoom_webhook_event vzwe where vzwe.event in ('meeting.participant_admitted') and vzwe.meeting_id = '".$m_id."') as participant_admitted_count,(select count(meeting_id) from vh_zoom_webhook_event vzwe where vzwe.event in ('meeting.participant_joined') and vzwe.meeting_id = '".$m_id."') as participant_joined_count, (select count(meeting_id) from vh_zoom_webhook_event vzwe where vzwe.event in ('meeting.participant_left') and vzwe.meeting_id = '".$m_id."') as participant_left_count) as w1) as w2;", array());

        return $waiting_room_data;
    } 

    public static function zoomGetMeetingUserList($meeting_id = '') {
        if(empty($meeting_id)) {
            return array();
        }

        $sql = "select vzwem.* from (select (select vzwe2.event from vh_zoom_webhook_event vzwe2 where vzwe2.meeting_id = vzwe.meeting_id and ((vzwe.user_id = vzwe2.user_id and vzwe2.event in ('meeting.participant_left')) or vzwe2.event in ('meeting.ended')) and vzwe2.event_ts >= vzwe.event_ts order by vzwe2.event_ts desc limit 1) as left_event, vzwe.* from vh_zoom_webhook_event vzwe where vzwe.event in ('meeting.participant_joined') and vzwe.meeting_id = ? order by vzwe.event_ts desc) as vzwem";
        
        $resList = array('host_user_count' => 0, 'other_user_count' => 0, 'meeting_status' => 0);    
        $zulist = array();
        $za_result = sqlStatementNoLog($sql, array($meeting_id));
        while ($frow = sqlFetchArray($za_result)) {
            $frow['payload'] = isset($frow['payload']) && !empty($frow['payload']) ? unserialize($frow['payload']) : array();

            $payloadData = isset($frow['payload']) && isset($frow['payload']['payload']) ? $frow['payload']['payload'] : array();
            $participantData = isset($payloadData['object']) && isset($payloadData['object']['participant']) ? $payloadData['object']['participant'] : array();

            $participant_user_id = isset($participantData['participant_user_id']) ? $participantData['participant_user_id'] : "";
            $participant_user_id = isset($participantData['participant_user_id']) ? $participantData['participant_user_id'] : "";
            $host_id = isset($payloadData['object']) && isset($payloadData['object']['host_id']) ? $payloadData['object']['host_id'] : "";

            $frow['is_in_meeting'] = 0;
            $frow['is_host'] = 0;
            if(empty($frow['left_event']) || is_null($frow['left_event'])) {
                $frow['is_in_meeting'] = 1;
            }

            if($participant_user_id == $host_id) {
                $frow['is_host'] = 1;
            } 

                
            if(!isset($zulist[$frow['user_id']])) {
                $zulist[$frow['user_id']] = $frow;
            }
        }

        $resList['items'] = $zulist;

        return $resList;
    }
}
