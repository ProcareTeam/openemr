drop table mat_visitsummary;

create table mat_visitsummary
as
SELECT
        CASE
                WHEN pc_title Like 'Spinal Decompression%' THEN 'Spinal Decompression'
                WHEN pc_title Like "Diagnostic Imaging" THEN "X-Rays"
                WHEN pc_title Like "Chiro SMART Session%" THEN "SMART Sessions"
                ELSE pc_title
        END AS "PC Title",
        CASE
                WHEN cat.pc_catid IN (16, 22, 30) THEN "Exams"
                WHEN cat.pc_catid IN (29, 53, 54) THEN "Telemedicine"
                WHEN cat.pc_catid = 2 THEN "In Office"
                WHEN cat.pc_catid = 3 THEN "Out Of Office"
                WHEN cat.pc_catid = 8 THEN "Lunch"
                WHEN cat.pc_catid = 11 THEN "Reserved"
                WHEN cat.pc_catid = 17 THEN "Nurse Visit"
                WHEN cat.pc_catid = 18 THEN "Adjustment With Therapy"
                WHEN cat.pc_catid = 19 THEN "Adjustment"
                WHEN cat.pc_catid = 20 THEN "Non-Compliance Cancellation"
                WHEN cat.pc_catid = 21 THEN "Lumber & Cervical Decompression"
                WHEN cat.pc_catid = 23 THEN "New Patient/ROF Cancel"
                WHEN cat.pc_catid = 24 THEN "Re-Exam"
                WHEN cat.pc_catid = 25 THEN "NCV/EMG"
                WHEN cat.pc_catid = 26 THEN "Chiro SMART Session"
                WHEN cat.pc_catid = 27 THEN "Injection Procedure"
                WHEN cat.pc_catid = 28 THEN "Lumber Decompression"
                WHEN cat.pc_catid = 31 THEN "Adjustment w/Rehab-Medical"
                WHEN cat.pc_catid = 33 THEN "Testing"
                WHEN cat.pc_catid = 34 THEN "Psychotherapy-New"
                WHEN cat.pc_catid = 35 THEN "Report Of Findings"
                WHEN cat.pc_catid = 36 THEN "X-Rays"
                WHEN cat.pc_catid = 37 THEN "Cervical Decompression"
                WHEN cat.pc_catid = 38 THEN "Botox"
                WHEN cat.pc_catid = 39 THEN "Weight Loss Cancellation"
                WHEN cat.pc_catid = 40 THEN "Cancel Within Day"
                WHEN cat.pc_catid = 41 THEN "Psychotherapy-Established"
                WHEN cat.pc_catid = 43 THEN "Adjustment-Monthly"
                WHEN cat.pc_catid = 44 THEN "SMART Session F/U"
                WHEN cat.pc_catid = 45 THEN "PPE"
                WHEN cat.pc_catid = 46 THEN "Message"
                WHEN cat.pc_catid = 47 THEN "Weather"
                WHEN cat.pc_catid = 48 THEN "New IP Consult"
                WHEN cat.pc_catid = 49 THEN "Landmark MMI"
                WHEN cat.pc_catid = 51 THEN "Surgery"
                WHEN cat.pc_catid = 55 THEN "Neuropsych-Eval"
                ELSE pc_title
        END AS "Appintment Category / Reason",
        pdata.pubpid AS pid,
        u.username as "ProviderID",
        pc_apptstatus AS "AppStatus_Code",
        (select list_options.title  from list_options where list_options.option_id =pd.pc_apptstatus and list_options.list_id like 'apptstat') as "Appointment Status",
        CONCAT(u.fname, ' ', u.lname) AS "ProviderName",
        CONCAT(pdata.fname, ' ', pdata.lname) AS PatientName,
        Week(Cast(pc_eventdate AS date)) AS "Week",
        Year(Cast(pc_eventdate AS date)) AS "Year",
        MonthName(Cast(pc_eventdate AS date)) AS "Month",
        Cast(pc_eventdate AS date) AS AppointmentDate,
        CASE
                WHEN Month(Cast(pc_eventdate AS date)) IN (1, 2, 3) THEN "Q1"
                WHEN Month(Cast(pc_eventdate AS date)) IN (4, 5, 6) THEN "Q2"
                WHEN Month(Cast(pc_eventdate AS date)) IN (7, 8, 9) THEN "Q3"
                WHEN Month(Cast(pc_eventdate AS date)) IN (10, 11, 12) THEN "Q4"
                ELSE null
        END AS "Quarter",
        (
        SELECT
                lo.title
        FROM
                list_options lo
        WHERE
                lo.list_id LIKE 'refsource'
                AND lo.option_id = fc.referral_source) AS "Referral Source",
                 fy.name AS Facility,
        (select list_options.title from list_options where option_id = u.taxonomy and list_id like 'taxonomy') as "ProviderType",DAYOFYEAR(pc_eventdate) as DayOfTheYear,
        u.npi,now() as DateRefreshed,uu.username as CaseManagerUsername,concat(uu.fname,' ',uu.lname) as CaseManagerName,fc.id as case_id,pd.pc_eid as "VisitID",
        cat.pc_catname as  AppointmentCategory_New
FROM
        openemr_postcalendar_events pd,
        users u,
        form_cases fc left outer join users uu on fc.vh_case_manager=uu.id,
        patient_data pdata,
        facility fy,openemr_postcalendar_categories cat
WHERE
        pd.pc_aid = u.id
        AND pc_case = fc.id
        AND pd.pc_pid = pdata.pid
        AND pd.pc_facility = fy.id
        and pd.pc_catid=cat.pc_catid
        AND length(pc_eventdate) > 0;

DROP TABLE IF EXISTS oemrprocare.MAT_VH_Provider_Reporting;

CREATE TABLE oemrprocare.MAT_VH_Provider_Reporting AS
SELECT
	CASE
		WHEN pc_title Like 'Spinal Decompression%'	THEN 'Spinal Decompression'
        WHEN pc_title Like "Diagnostic Imaging" 	THEN "X-Rays"
        WHEN pc_title Like "Chiro SMART Session%"	THEN "SMART Sessions"
		ELSE pc_title
	END AS "PC Title",
	CASE 
		WHEN pc_catid IN (16,22,30) THEN "Exams"
		WHEN pc_catid IN (29,53,54) THEN "Telemedicine"
		WHEN pc_catid = 2  THEN "In Office"
		WHEN pc_catid = 3  THEN "Out Of Office"
		WHEN pc_catid = 8  THEN "Lunch"
		WHEN pc_catid = 11 THEN "Reserved"
		WHEN pc_catid = 17 THEN "Nurse Visit"
		WHEN pc_catid = 18 THEN "Adjustment With Therapy"
		WHEN pc_catid = 19 THEN "Adjustment"
		WHEN pc_catid = 20 THEN "Non-Compliance Cancellation"
		WHEN pc_catid = 21 THEN "Lumber & Cervical Decompression"
		WHEN pc_catid = 23 THEN "New Patient/ROF Cancel"
		WHEN pc_catid = 24 THEN "Re-Exam"
		WHEN pc_catid = 25 THEN "NCV/EMG"
		WHEN pc_catid = 26 THEN "Chiro SMART Session"
		WHEN pc_catid = 27 THEN "Injection Procedure"
		WHEN pc_catid = 28 THEN "Lumber Decompression"
		WHEN pc_catid = 31 THEN "Adjustment w/Rehab-Medical"
		WHEN pc_catid = 33 THEN "Testing"
		WHEN pc_catid = 34 THEN "Psychotherapy-New"
		WHEN pc_catid = 35 THEN "Report Of Findings"
		WHEN pc_catid = 36 THEN "X-Rays"
		WHEN pc_catid = 37 THEN "Cervical Decompression"
		WHEN pc_catid = 38 THEN "Botox"
		WHEN pc_catid = 39 THEN "Weight Loss Cancellation"
		WHEN pc_catid = 40 THEN "Cancel Within Day"
		WHEN pc_catid = 41 THEN "Psychotherapy-Established"
		WHEN pc_catid = 43 THEN "Adjustment-Monthly"
		WHEN pc_catid = 44 THEN "SMART Session F/U"
		WHEN pc_catid = 45 THEN "PPE"
		WHEN pc_catid = 46 THEN "Message"
		WHEN pc_catid = 47 THEN "Weather"
		WHEN pc_catid = 48 THEN "New IP Consult"
		WHEN pc_catid = 49 THEN "Landmark MMI"
		WHEN pc_catid = 51 THEN "Surgery"
		WHEN pc_catid = 55 THEN "Neuropsych-Eval"
		ELSE pc_title 
	END AS "Appintment Category / Reason",
	CASE WHEN (pd.pc_apptstatus = '?' OR pd.pc_apptstatus = '%' OR pd.pc_apptstatus = 'x') 
		THEN 1
		ELSE 0
	END AS "Bad Cancels",
	CASE WHEN (pd.pc_apptstatus = '?' OR pd.pc_apptstatus = '%') 
		THEN 1
		ELSE 0
	END AS "Bad Cancels1",
	CASE WHEN (pd.pc_apptstatus = 'x' OR pd.pc_apptstatus = '%') 
		THEN 1
		ELSE 0
	END AS "OnlyCancelled",
	pc_aid AS "PC AID",
	pc_catid,
	pdata.pubpid AS pid,
	u.username,
	pc_apptstatus AS "PC AppStatus",
	CONCAT(u.fname, ' ', u.lname) AS Name,
	CONCAT(pdata.fname, ' ', pdata.lname) AS PatientName,
	Week(Cast(pc_eventdate AS date)) AS "Week",
	Year(Cast(pc_eventdate AS date)) AS "Year",
	MonthName(Cast(pc_eventdate AS date)) AS "Month",
	Cast(pc_eventdate AS date) AS AppointmentDate,
	CASE
		WHEN Month(Cast(pc_eventdate AS date)) IN (1, 2, 3) THEN "Q1"
		WHEN Month(Cast(pc_eventdate AS date)) IN (4, 5, 6) THEN "Q2"
		WHEN Month(Cast(pc_eventdate AS date)) IN (7, 8, 9) THEN "Q3"
		WHEN Month(Cast(pc_eventdate AS date)) IN (10,11,12) THEN "Q4"
		ELSE null
	END AS "Quarter",
	(SELECT lo.title FROM list_options lo
		WHERE lo.list_id LIKE 'refsource' AND lo.option_id = fc.referral_source) AS "Referral Source",
	fy.name AS Facility,
	fy.id AS FacilityID
FROM oemrprocare.openemr_postcalendar_events pd,
	oemrprocare.users u,
	oemrprocare.form_cases fc,
	oemrprocare.patient_data pdata,
	oemrprocare.facility fy
WHERE pd.pc_aid = u.id
	AND pc_case = fc.id
	AND pd.pc_pid = pdata.id
	AND pd.pc_facility = fy.id
 	AND length(pc_eventdate) > 0
;


DROP TABLE IF EXISTS oemrchiro.MAT_VH_Provider_Reporting;

CREATE TABLE oemrchiro.MAT_VH_Provider_Reporting AS
SELECT
	CASE
		WHEN pc_title Like 'Spinal Decompression%'	THEN 'Spinal Decompression'
        WHEN pc_title Like "Diagnostic Imaging" 	THEN "X-Rays"
        WHEN pc_title Like "Chiro SMART Session%"	THEN "SMART Sessions"
		ELSE pc_title
	END AS "PC Title",
	CASE 
		WHEN pc_catid IN (16,22,30) THEN "Exams"
		WHEN pc_catid IN (29,53,54) THEN "Telemedicine"
		WHEN pc_catid = 2  THEN "In Office"
		WHEN pc_catid = 3  THEN "Out Of Office"
		WHEN pc_catid = 8  THEN "Lunch"
		WHEN pc_catid = 11 THEN "Reserved"
		WHEN pc_catid = 17 THEN "Nurse Visit"
		WHEN pc_catid = 18 THEN "Adjustment With Therapy"
		WHEN pc_catid = 19 THEN "Adjustment"
		WHEN pc_catid = 20 THEN "Non-Compliance Cancellation"
		WHEN pc_catid = 21 THEN "Lumber & Cervical Decompression"
		WHEN pc_catid = 23 THEN "New Patient/ROF Cancel"
		WHEN pc_catid = 24 THEN "Re-Exam"
		WHEN pc_catid = 25 THEN "NCV/EMG"
		WHEN pc_catid = 26 THEN "Chiro SMART Session"
		WHEN pc_catid = 27 THEN "Injection Procedure"
		WHEN pc_catid = 28 THEN "Lumber Decompression"
		WHEN pc_catid = 31 THEN "Adjustment w/Rehab-Medical"
		WHEN pc_catid = 33 THEN "Testing"
		WHEN pc_catid = 34 THEN "Psychotherapy-New"
		WHEN pc_catid = 35 THEN "Report Of Findings"
		WHEN pc_catid = 36 THEN "X-Rays"
		WHEN pc_catid = 37 THEN "Cervical Decompression"
		WHEN pc_catid = 38 THEN "Botox"
		WHEN pc_catid = 39 THEN "Weight Loss Cancellation"
		WHEN pc_catid = 40 THEN "Cancel Within Day"
		WHEN pc_catid = 41 THEN "Psychotherapy-Established"
		WHEN pc_catid = 43 THEN "Adjustment-Monthly"
		WHEN pc_catid = 44 THEN "SMART Session F/U"
		WHEN pc_catid = 45 THEN "PPE"
		WHEN pc_catid = 46 THEN "Message"
		WHEN pc_catid = 47 THEN "Weather"
		WHEN pc_catid = 48 THEN "New IP Consult"
		WHEN pc_catid = 49 THEN "Landmark MMI"
		WHEN pc_catid = 51 THEN "Surgery"
		WHEN pc_catid = 55 THEN "Neuropsych-Eval"
		ELSE pc_title 
	END AS "Appintment Category / Reason",
	CASE WHEN (pd.pc_apptstatus = '?' OR pd.pc_apptstatus = '%' OR pd.pc_apptstatus = 'x') 
		THEN 1
		ELSE 0
	END AS "Bad Cancels",
	CASE WHEN (pd.pc_apptstatus = '?' OR pd.pc_apptstatus = '%') 
		THEN 1
		ELSE 0
	END AS "Bad Cancels1",
	CASE WHEN (pd.pc_apptstatus = 'x' OR pd.pc_apptstatus = '%') 
		THEN 1
		ELSE 0
	END AS "OnlyCancelled",
	pc_aid AS "PC AID",
	pc_catid,
	pdata.pubpid AS pid,
	u.username,
	pc_apptstatus AS "PC AppStatus",
	CONCAT(u.fname, ' ', u.lname) AS Name,
	CONCAT(pdata.fname, ' ', pdata.lname) AS PatientName,
	Week(Cast(pc_eventdate AS date)) AS "Week",
	Year(Cast(pc_eventdate AS date)) AS "Year",
	MonthName(Cast(pc_eventdate AS date)) AS "Month",
	Cast(pc_eventdate AS date) AS AppointmentDate,
	CASE
		WHEN Month(Cast(pc_eventdate AS date)) IN (1, 2, 3) THEN "Q1"
		WHEN Month(Cast(pc_eventdate AS date)) IN (4, 5, 6) THEN "Q2"
		WHEN Month(Cast(pc_eventdate AS date)) IN (7, 8, 9) THEN "Q3"
		WHEN Month(Cast(pc_eventdate AS date)) IN (10,11,12) THEN "Q4"
		ELSE null
	END AS "Quarter",
	(SELECT lo.title FROM list_options lo
		WHERE lo.list_id LIKE 'refsource' AND lo.option_id = fc.referral_source) AS "Referral Source",
	fy.name AS Facility,
	fy.id AS FacilityID
FROM oemrchiro.openemr_postcalendar_events pd,
	oemrchiro.users u,
	oemrchiro.form_cases fc,
	oemrchiro.patient_data pdata,
	oemrchiro.facility fy
WHERE pd.pc_aid = u.id
	AND pc_case = fc.id
	AND pd.pc_pid = pdata.id
	AND pd.pc_facility = fy.id
 	AND length(pc_eventdate) > 0
;


DROP TABLE IF EXISTS oemrprocare.MAT_VH_Provider_Reporting_AptTypeMix;

CREATE TABLE oemrprocare.MAT_VH_Provider_Reporting_AptTypeMix AS
SELECT
		username,
		YEAR(CAST(ope.pc_eventDate As date)) AS Year,
		WEEK(CAST(ope.pc_eventDate As date)) AS Week,
		CASE
			WHEN (	ic1.ins_type_code 		IN (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,17,18,19,21,22,24)
					OR ic2.ins_type_code 	IN (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,17,18,19,21,22,24)
					OR ic3.ins_type_code 	IN (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,17,18,19,21,22,24))	THEN 'Major Medical'
			WHEN (ic1.ins_type_code IN (16) or ic2.ins_type_code IN (16) or ic3.ins_type_code IN (16)) 	THEN 'Vehicle PIP'
			WHEN (ic1.ins_type_code IN (20) or ic2.ins_type_code IN (20) or ic3.ins_type_code IN (20)) 	THEN 'Liability'
			WHEN (ic1.ins_type_code IN (23) or ic2.ins_type_code IN (23) or ic3.ins_type_code IN (23)) 	THEN 'Employer'
			WHEN (ic1.ins_type_code IN (25) or ic2.ins_type_code IN (25) or ic3.ins_type_code IN (25)) 	THEN 'WorkersComp'
			WHEN cs.cash THEN 'Cash Case'
		END Insurance1Type,
		COUNT(1) AS RecordCount
FROM oemrprocare.openemr_postcalendar_events ope
INNER JOIN oemrprocare.form_cases cs on ope.pc_case = cs.id
LEFT JOIN oemrprocare.insurance_data insd1 ON insd1.id = cs.ins_data_id1
LEFT JOIN oemrprocare.insurance_companies ic1 ON insd1.provider = ic1.id
LEFT JOIN oemrprocare.insurance_data insd2 ON insd2.id = cs.ins_data_id2
LEFT JOIN oemrprocare.insurance_companies ic2 ON insd2.provider = ic2.id
LEFT JOIN oemrprocare.insurance_data insd3 ON insd3.id = cs.ins_data_id3
LEFT JOIN oemrprocare.insurance_companies ic3 ON insd3.provider = ic3.id,
	oemrprocare.users u
WHERE ope.pc_aid=u.id
	AND ope.pc_apptstatus IN ('>', '<', '@', '~')
	AND ope.pc_eventDate >= ((curdate() - interval (weekday(curdate()) + 1 ) day) - interval 13 week)
	AND ope.pc_eventDate <  ((curdate() - interval (weekday(curdate()) + 1 ) day) - interval 0  week)

GROUP BY 1, 2, 3, 4
ORDER BY 1, 2, 3
;


DROP TABLE IF EXISTS oemrchiro.MAT_VH_Provider_Reporting_AptTypeMix;

CREATE TABLE oemrchiro.MAT_VH_Provider_Reporting_AptTypeMix AS
SELECT
		username,
		YEAR(CAST(ope.pc_eventDate As date)) AS Year,
		WEEK(CAST(ope.pc_eventDate As date)) AS Week,
		CASE
			WHEN (	ic1.ins_type_code 		IN (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,17,18,19,21,22,24)
					OR ic2.ins_type_code 	IN (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,17,18,19,21,22,24)
					OR ic3.ins_type_code 	IN (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,17,18,19,21,22,24))	THEN 'Major Medical'
			WHEN (ic1.ins_type_code IN (16) or ic2.ins_type_code IN (16) or ic3.ins_type_code IN (16)) 	THEN 'Vehicle PIP'
			WHEN (ic1.ins_type_code IN (20) or ic2.ins_type_code IN (20) or ic3.ins_type_code IN (20)) 	THEN 'Liability'
			WHEN (ic1.ins_type_code IN (23) or ic2.ins_type_code IN (23) or ic3.ins_type_code IN (23)) 	THEN 'Employer'
			WHEN (ic1.ins_type_code IN (25) or ic2.ins_type_code IN (25) or ic3.ins_type_code IN (25)) 	THEN 'WorkersComp'
			WHEN cs.cash THEN 'Cash Case'
		END Insurance1Type,
		COUNT(1) AS RecordCount
FROM oemrchiro.openemr_postcalendar_events ope
INNER JOIN oemrchiro.form_cases cs on ope.pc_case = cs.id
LEFT JOIN oemrchiro.insurance_data insd1 ON insd1.id = cs.ins_data_id1
LEFT JOIN oemrchiro.insurance_companies ic1 ON insd1.provider = ic1.id
LEFT JOIN oemrchiro.insurance_data insd2 ON insd2.id = cs.ins_data_id2
LEFT JOIN oemrchiro.insurance_companies ic2 ON insd2.provider = ic2.id
LEFT JOIN oemrchiro.insurance_data insd3 ON insd3.id = cs.ins_data_id3
LEFT JOIN oemrchiro.insurance_companies ic3 ON insd3.provider = ic3.id,
	oemrchiro.users u
WHERE ope.pc_aid=u.id
	AND ope.pc_apptstatus IN ('>', '<', '@', '~')
	AND ope.pc_eventDate >= ((curdate() - interval (weekday(curdate()) + 1 ) day) - interval 13 week)
	AND ope.pc_eventDate <  ((curdate() - interval (weekday(curdate()) + 1 ) day) - interval 0  week)
GROUP BY 1, 2, 3, 4
ORDER BY 1, 2, 3
;

drop table mat_vh_provider_master;

create table mat_vh_provider_master
as
select (select list_options.title from list_options where option_id = u.taxonomy and list_id like 'taxonomy') as "ProviderType",u.id,u.username,CONCAT(u.fname, ' ', u.lname) AS "ProviderName",u.npi,u.taxonomy
from users u
where u.taxonomy is not null
and length(u.username)>0
and length(u.npi)>0
and u.authorized=1;
