\timing on
\echo '=== Q1 rappel convention (cron quotidien, toutes activités)'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF) SELECT e.* FROM mdl_stage_entry e JOIN mdl_modules m ON m.name = 'stage'
 JOIN mdl_course_modules cm ON cm.instance = e.stageid AND cm.module = m.id AND cm.deletioninprogress = 0
 WHERE e.datestart > 0 AND e.datestart >= 1750000000 AND e.datestart <= 1750000000+8*86400
 AND e.conventionstatus NOT IN (3,4,6) AND e.status <> -2 AND e.conventionremindertime IS NULL ORDER BY e.datestart ASC;
\echo '=== Q2 invitation maitre de stage (cron quotidien)'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF) SELECT e.* FROM mdl_stage_entry e JOIN mdl_stage s ON s.id = e.stageid JOIN mdl_modules m ON m.name = 'stage'
 JOIN mdl_course_modules cm ON cm.instance = s.id AND cm.module = m.id AND cm.deletioninprogress = 0
 JOIN mdl_stage_theme t ON t.id = e.themeid JOIN mdl_stage_convention_detail d ON d.entryid = e.id
 WHERE e.datestart > 0 AND e.datestart <= 1750000000 AND e.status IN (0,1) AND e.conventionstatus IN (3,4,6)
 AND e.tutorbypassed = 0 AND e.tutorrequesttime = 0 AND (e.tutortime IS NULL OR e.tutortime = 0)
 AND s.tutorevaluationenabled = 1 AND t.tutorevaluationenabled = 1 AND d.tutoremail IS NOT NULL AND d.tutoremail <> '' ORDER BY e.datestart ASC;
\echo '=== Q3 liste des stages, recherche par nom, tri étudiant (une promotion = 10 000 saisies)'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF) SELECT e.* FROM mdl_stage_entry e JOIN mdl_user u ON u.id = e.userid LEFT JOIN mdl_stage_theme t ON t.id = e.themeid
 WHERE e.stageid = 20 AND (u.firstname||' '||u.lastname) ILIKE '%nom12%' ORDER BY u.lastname, u.firstname ASC, e.id ASC;
\echo '=== Q3b liste sans filtre (charge toute la promotion avant pagination PHP)'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF) SELECT e.* FROM mdl_stage_entry e JOIN mdl_user u ON u.id = e.userid LEFT JOIN mdl_stage_theme t ON t.id = e.themeid
 WHERE e.stageid = 20 ORDER BY e.timecreated DESC, e.id DESC;
\echo '=== Q4 file des conventions DEVE'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF) SELECT e.* FROM mdl_stage_entry e JOIN mdl_user u ON u.id = e.userid LEFT JOIN mdl_stage_theme t ON t.id = e.themeid
 WHERE e.stageid = 20 AND e.conventionstatus != 0 AND e.conventionstatus != 4 AND e.conventionstatus != 5 ORDER BY e.conventionrequesttime DESC, e.id DESC;
\echo '=== Q5 progress_cache::prime, paquet de 250 étudiants'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF) SELECT * FROM mdl_stage_entry WHERE stageid = 20 AND userid IN (SELECT generate_series(4751,5000)) ORDER BY timecreated DESC, id DESC;
\echo '=== Q6 nombre de réponses d une question (suppression de question)'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF) SELECT count(*) FROM mdl_stage_answer WHERE questionid = 571;
\echo '=== Q7 réponses d une saisie'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF) SELECT * FROM mdl_stage_answer WHERE entryid = 123456;
\echo '=== Q8 jeton maitre de stage'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF) SELECT * FROM mdl_stage_entry WHERE tutortoken = 'abc';
\echo '=== Q9 saisies d un étudiant tous stages (privacy / stagesynthesis)'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF) SELECT * FROM mdl_stage_entry WHERE userid = 4999;
\echo '=== Q10 stages suivis par un enseignant (teacherid)'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF) SELECT * FROM mdl_stage_entry_teacher WHERE teacherid = 5100;
