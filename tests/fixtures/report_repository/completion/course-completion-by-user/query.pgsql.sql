SELECT u.firstname || ' ' || u.lastname AS learner,
       cat.name AS category,
       c.fullname AS course,
       CASE
           WHEN cc.timecompleted > 0 THEN 'Complete'
           WHEN cc.timestarted > 0 THEN 'In progress'
           ELSE 'Not yet started'
       END AS status,
       CASE
           WHEN cc.timecompleted > 0 THEN to_char(to_timestamp(cc.timecompleted), 'YYYY-MM-DD')
           ELSE ''
       END AS completed
FROM prefix_course_completions cc
JOIN prefix_user u ON u.id = cc.userid AND u.deleted = 0
JOIN prefix_course c ON c.id = cc.course
JOIN prefix_course_categories cat ON cat.id = c.category
WHERE c.enablecompletion = 1
%%FILTER_COURSES:c.id%%
ORDER BY u.lastname, u.firstname, cat.name, c.fullname
