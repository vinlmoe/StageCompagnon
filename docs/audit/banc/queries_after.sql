\echo '=== Q1 rappel convention (avec index datestart)'
EXPLAIN (ANALYZE, COSTS OFF) SELECT e.* FROM mdl_stage_entry e JOIN mdl_modules m ON m.name = 'stage'
 JOIN mdl_course_modules cm ON cm.instance = e.stageid AND cm.module = m.id AND cm.deletioninprogress = 0
 WHERE e.datestart > 0 AND e.datestart >= 1750000000 AND e.datestart <= 1750000000+8*86400
 AND e.conventionstatus NOT IN (3,4,6) AND e.status <> -2 AND e.conventionremindertime IS NULL ORDER BY e.datestart ASC;
\echo '=== Q2 invitation maitre de stage (plancher 60 j après la fin, LIMIT 200)'
EXPLAIN (ANALYZE, COSTS OFF) SELECT e.* FROM mdl_stage_entry e JOIN mdl_stage s ON s.id = e.stageid JOIN mdl_modules m ON m.name = 'stage'
 JOIN mdl_course_modules cm ON cm.instance = s.id AND cm.module = m.id AND cm.deletioninprogress = 0
 JOIN mdl_stage_theme t ON t.id = e.themeid JOIN mdl_stage_convention_detail d ON d.entryid = e.id
 WHERE e.datestart > 0 AND e.datestart <= 1793436037 AND (e.dateend IS NULL OR e.dateend = 0 OR e.dateend >= 1793436037 - 60*86400) AND e.status IN (0,1) AND e.conventionstatus IN (3,4,6)
 AND e.tutorbypassed = 0 AND e.tutorrequesttime = 0 AND (e.tutortime IS NULL OR e.tutortime = 0)
 AND s.tutorevaluationenabled = 1 AND t.tutorevaluationenabled = 1 AND d.tutoremail IS NOT NULL AND d.tutoremail <> '' ORDER BY e.datestart ASC, e.id ASC LIMIT 200;
\echo '=== Q3 liste paginée : COUNT puis page'
EXPLAIN (ANALYZE, COSTS OFF) SELECT COUNT(e.id) FROM mdl_stage_entry e JOIN mdl_user u ON u.id = e.userid WHERE e.stageid = 20 AND e.status < 3;
EXPLAIN (ANALYZE, COSTS OFF) SELECT e.* FROM mdl_stage_entry e JOIN mdl_user u ON u.id = e.userid LEFT JOIN mdl_stage_theme t ON t.id = e.themeid
 WHERE e.stageid = 20 AND e.status < 3 ORDER BY u.lastname, u.firstname ASC, e.id ASC LIMIT 40 OFFSET 0;
