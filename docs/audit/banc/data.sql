-- Tables Moodle minimales.
CREATE TABLE mdl_user (id BIGSERIAL PRIMARY KEY, firstname VARCHAR(100), lastname VARCHAR(100), email VARCHAR(100), deleted SMALLINT DEFAULT 0);
CREATE TABLE mdl_modules (id BIGSERIAL PRIMARY KEY, name VARCHAR(20));
CREATE TABLE mdl_course_modules (id BIGSERIAL PRIMARY KEY, course BIGINT, module BIGINT, instance BIGINT, deletioninprogress SMALLINT DEFAULT 0);
CREATE INDEX ON mdl_course_modules(module); CREATE INDEX ON mdl_course_modules(instance);
INSERT INTO mdl_modules(name) SELECT 'mod'||g FROM generate_series(1,40) g; INSERT INTO mdl_modules(name) VALUES ('stage');
-- 20 promotions (5 actives + 15 années d'historique), 250 étudiants chacune, 60 thématiques.
INSERT INTO mdl_user(firstname,lastname,email) SELECT 'Prénom'||g,'Nom'||g,'u'||g||'@ex.fr' FROM generate_series(1,5500) g;
INSERT INTO mdl_stage(course,name,timecreated,timemodified,tutorevaluationenabled) SELECT g,'Promo '||g,0,0,1 FROM generate_series(1,20) g;
INSERT INTO mdl_course_modules(course,module,instance) SELECT g,(SELECT id FROM mdl_modules WHERE name='stage'),g FROM generate_series(1,20) g;
INSERT INTO mdl_course_modules(course,module,instance) SELECT g%500,1+g%40,g FROM generate_series(1,50000) g;
INSERT INTO mdl_stage_theme(stageid,name,mandatory,timecreated,timemodified)
  SELECT s, 'Thématique '||t, (t%3=0)::int, 0, 0 FROM generate_series(1,20) s, generate_series(1,60) t;
-- 40 stages par étudiant : 20*250*40 = 200 000 saisies.
INSERT INTO mdl_stage_entry(stageid,themeid,studyyear,userid,structure,datestart,dateend,declaredduration,status,
   conventionstatus,conventionrequesttime,tutorrequesttime,tutorbypassed,timecreated,timemodified)
SELECT s, (s-1)*60 + 1 + (k*7+u)%60, 1+k%5, (s-1)*250+u, 'Clinique '||(k%300),
       1700000000::bigint + k*864000 + u*3600 + (s-15)*86400*120, 1700000000::bigint + k*864000 + u*3600 + (s-15)*86400*120 + 14*86400,
       10, (ARRAY[0,1,2,3,3,3,3,-1,-2])[1+(k+u)%9], (ARRAY[0,1,2,3,4,5,6,-1])[1+(k*3+u)%8],
       1700000000 + k*1000, ((k+u)%2)*1700000000, 0, 1700000000 + k*86400 + u, 0
  FROM generate_series(1,20) s, generate_series(1,250) u, generate_series(1,40) k;
INSERT INTO mdl_stage_convention_detail(entryid, tutoremail, stagetype, timecreated, timemodified)
  SELECT id, 'tuteur'||id||'@ex.fr', 'obligatoire',0,0 FROM mdl_stage_entry WHERE conventionstatus <> 0;
INSERT INTO mdl_stage_entry_teacher(stageid,studentid,teacherid)
  SELECT s,(s-1)*250+u, 5001+((s*7+u)%300) FROM generate_series(1,20) s, generate_series(1,250) u;
INSERT INTO mdl_stage_question(stageid,themeid,evaltype,name,qtype,sortorder,timecreated,timemodified)
  SELECT s,(s-1)*60+1,(ARRAY['student','teacher','tutor'])[1+q%3],'Q'||q,'choice',q,0,0 FROM generate_series(1,20) s, generate_series(1,30) q;
-- ~10 réponses par saisie : 2 000 000 lignes.
INSERT INTO mdl_stage_answer(entryid,questionid,answertext,timecreated,timemodified)
  SELECT e.id, (e.stageid-1)*30 + q, 'réponse',0,0 FROM mdl_stage_entry e, generate_series(1,10) q;
VACUUM ANALYZE;
