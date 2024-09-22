<?php

function getTimesList() {
    $schedule_start = $GLOBALS['schedule_start'];
    $schedule_end = $GLOBALS['schedule_end'];

    // $times is an array of associative arrays, where each sub-array
    // has keys 'hour', 'minute' and 'mer'.
    //
    $times = array();

    // For each hour in the schedule...
    //
    for ($blocknum = $schedule_start; $blocknum <= $schedule_end; $blocknum++) {
        $mer = ($blocknum >= 12) ? 'pm' : 'am';

        // $minute is an array of time slot strings within this hour.
        $minute = array('00');

        for ($minutes = $GLOBALS['calendar_interval']; $minutes <= 60; $minutes += $GLOBALS['calendar_interval']) {
            if ($minutes <= '9') {
                $under_ten = "0" . $minutes;
                array_push($minute, "$under_ten");
            } elseif ($minutes >= '60') {
                break;
            } else {
                array_push($minute, "$minutes");
            }
        }

        foreach ($minute as $m) {
            array_push($times, array("hour" => $blocknum, "minute" => $m, "mer" => $mer));
        }
    }

    return $times;
}

function getBlockData($AEVENTS = array()) {
    $inEvents = array();
    foreach ($AEVENTS as $date => $events) {
        foreach ($events as $event) {
            // ignore IN event
            if (($event['catid'] != 2)) { continue; }

            // skip events without an ID (why they are in the loop, I have no idea)
            if ($event['eid'] == "") { continue; }

            $times = getTimesList();
            $tmpTime = $times[0];
            $calStartMin = ($tmpTime['hour'] * 60) + $tmpTime['minute'];
            $tmpTime = $times[count($times)-1];
            $calEndMin = ($tmpTime['hour'] * 60) + $tmpTime['minute'];

            // specially handle all-day events
            if ($event['alldayevent'] == 1) {
                if (strlen($tmpTime['hour']) < 2) { $tmpTime['hour'] = "0".$tmpTime['hour']; }
                if (strlen($tmpTime['minute']) < 2) { $tmpTime['minute'] = "0".$tmpTime['minute']; }
                $event['startTime'] = $tmpTime['hour'].":".$tmpTime['minute'].":00";
                $event['duration'] = ($calEndMin - $calStartMin) * 60;  // measured in seconds
            }

            // figure the start time and minutes (from midnight)
            $starth = substr($event['startTime'], 0, 2);
            $startm = substr($event['startTime'], 3, 2);
            $eStartMin = $starth * 60 + $startm;

            if ($event['catid'] == 2) {
                // locate a matching OUT for this specific IN
                $found = false;
                $outMins = 0;
                foreach ($events as $outevent) {
                    // skip events for other providers
                    if ($event['aid'] != $outevent['aid']) { continue; }
                    // skip events with blank IDs
                    if ($outevent['eid'] == "") { continue; }

                    if ($outevent['eid'] == $event['eid']) { $found = true; continue; }
                    if (($found == true) && ($outevent['catid'] == 3)) {
                        // calculate the duration from this event to the outevent
                        $outH = substr($outevent['startTime'], 0, 2);
                        $outM = substr($outevent['startTime'], 3, 2);
                        $outMins = ($outH * 60) + $outM;
                        $event['duration'] = ($outMins - $eStartMin) * 60; // duration is in seconds
                        $found = 2;
                        break;
                    }
                }
                if ($outMins == 0) {
                    // no OUT was found so this event's duration goes
                    // until the end of the day
                    $event['duration'] = ($calEndMin - $eStartMin) * 60; // duration is in seconds
                }
            }

            if(!isset($inEvents[$event['aid']])) $inEvents[$event['aid']] = array();

            $event['cEventDate'] = $date;

            $inEvents[$event['aid']][] = $event;
        }
    }

    //exit();

    return $inEvents;
}

function getDefaultFacility1($providers = array(), $date = '', $inEvents = array() ) {
    $res = array();
    foreach ($providers as $providerid) {
        if(!empty($inEvents) && isset($inEvents[$providerid])) {
            foreach ($inEvents[$providerid] as $iei => $event) {
                // create a numeric start and end for comparison
                $starth = substr($event['startTime'], 0, 2);
                $startm = substr($event['startTime'], 3, 2);

                $eDate = DateTime::createFromFormat('Y-m-d', $event['cEventDate']);

                $eStartTime = DateTime::createFromFormat('Y-m-d H:i', $eDate->format("Y-m-d")." ".$starth .":". $startm);
                $eEndTime = DateTime::createFromFormat('Y-m-d H:i', $eDate->format("Y-m-d")." ".$starth .":". $startm);
                $eEndTime->modify("+".($event['duration']/60)." minute");

                if(!isset($res['p'.$providerid])) $res['p'.$providerid] = array();

                $res['p'.$providerid][] = array(
                	'eid' => $event['eid'],
                	'startTime' => $eStartTime->format("Y-m-d H:i:s"),
                	'endTime' => $eEndTime->format("Y-m-d H:i:s"),
                	'endTime1' => $eEndTime->format("c"),
                	'facility' => $event['facility']['id']
                );
            }
        }
    }

    return $res;
}

function getDefaultFacility($providers = array(), $slottime, $inEvents = array() ) {
    $res = array();

    foreach ($providers as $providerid) {
        if(!empty($inEvents) && isset($inEvents[$providerid])) {
            if(is_array($inEvents[$providerid]) && !empty($inEvents[$providerid])) {

                if(!isset($slottime['date'])) $slottime['date'] = date('Ymd');

                if($slottime['hour'] > 12) {
                    $cTime = DateTime::createFromFormat('Ymd H:i', $slottime['date'] ." ". $slottime['hour'].":".$slottime['minute']);
                } else {
                    $cTime = DateTime::createFromFormat('Ymd h:i a', $slottime['date'] ." ".$slottime['hour'].":".$slottime['minute']." ".$slottime['mer']);
                }

                $default_facility = '';

                foreach ($inEvents[$providerid] as $iei => $event) {
                    // create a numeric start and end for comparison
                    $starth = substr($event['startTime'], 0, 2);
                    $startm = substr($event['startTime'], 3, 2);

                    $eStartTime = DateTime::createFromFormat('H:i', $starth .":". $startm);
                    $eEndTime = DateTime::createFromFormat('H:i', $starth .":". $startm);
                    $eEndTime->modify("+".($event['duration']/60)." minute");

                    if($eStartTime <= $cTime && $eEndTime >= $cTime) {
                        if(isset($event['facility']) && isset($event['facility']['id'])) {
                            $default_facility = $event['facility']['id'];
                            break;
                        }
                    }
                }

                if(!empty($default_facility)) $res[$providerid] = $default_facility;
            }
        }
    }

    return $res;
}
