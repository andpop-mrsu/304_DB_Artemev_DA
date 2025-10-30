#!/bin/bash

chcp 65001

sqlite3 movies_rating.db < db_init.sql

echo "1. Найти все пары пользователей, оценивших один и тот же фильм. Устранить дубликаты, проверить отсутствие пар с самим собой. Для каждой пары должны быть указаны имена пользователей и название фильма, который они ценили. В списке оставить первые 100 записей."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "
SELECT u1.name AS user1_name, u2.name AS user2_name, m.title AS movie_title
FROM ratings r1
JOIN ratings r2 ON r1.movie_id = r2.movie_id AND r1.user_id < r2.user_id
JOIN users u1 ON r1.user_id = u1.id
JOIN users u2 ON r2.user_id = u2.id
JOIN movies m ON r1.movie_id = m.id
LIMIT 100;
"
echo " "

echo "2. Найти 10 самых старых оценок от разных пользователей, вывести названия фильмов, имена пользователей, оценку, дату отзыва в формате ГГГГ-ММ-ДД."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "
SELECT m.title AS movie_title, u.name AS user_name, r.rating, date(r.timestamp, 'unixepoch') AS review_date
FROM ratings r
JOIN movies m ON r.movie_id = m.id
JOIN users u ON r.user_id = u.id
ORDER BY r.timestamp ASC
LIMIT 10;
"
echo " "

echo "3. Вывести в одном списке все фильмы с максимальным средним рейтингом и все фильмы с минимальным средним рейтингом. Общий список отсортировать по году выпуска и названию фильма. В зависимости от рейтинга в колонке \"Рекомендуем\" для фильмов должно быть написано \"Да\" или \"Нет\"."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "
WITH avg_ratings AS (
  SELECT m.id, m.title, m.year, ROUND(AVG(r.rating), 2) AS avg_rating
  FROM movies m
  JOIN ratings r ON m.id = r.movie_id
  GROUP BY m.id, m.title, m.year
),
extremes AS (
  SELECT MAX(avg_rating) AS max_rating, MIN(avg_rating) AS min_rating FROM avg_ratings
)
SELECT ar.title, ar.year, ar.avg_rating,
  CASE 
    WHEN ar.avg_rating = ext.max_rating THEN 'Да'
    WHEN ar.avg_rating = ext.min_rating THEN 'Нет'
    ELSE ''
  END AS 'Рекомендуем'
FROM avg_ratings ar, extremes ext
WHERE ar.avg_rating = ext.max_rating OR ar.avg_rating = ext.min_rating
ORDER BY ar.year, ar.title;
"
echo " "

echo "4. Вычислить количество оценок и среднюю оценку, которую дали фильмам пользователи-мужчины в период с 2011 по 2014 год."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "
SELECT COUNT(*) as num_ratings, ROUND(AVG(r.rating),2) as avg_rating
FROM ratings r
JOIN users u ON r.user_id = u.id
WHERE u.gender = 'male' AND date(r.timestamp, 'unixepoch') BETWEEN '2011-01-01' AND '2014-12-31';
"
echo " "

echo "5. Составить список фильмов с указанием средней оценки и количества пользователей, которые их оценили. Полученный список отсортировать по году выпуска и названиям фильмов. В списке оставить первые 20 записей."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "
SELECT m.title, m.year, ROUND(AVG(r.rating),2) AS avg_rating, COUNT(DISTINCT r.user_id) AS num_users
FROM movies m
LEFT JOIN ratings r ON m.id = r.movie_id
GROUP BY m.id, m.title, m.year
ORDER BY m.year, m.title
LIMIT 20;
"
echo " "

echo "6. Определить самый распространенный жанр фильма и количество фильмов в этом жанре. Отдельную таблицу для жанров не использовать, жанры нужно извлекать из таблицы movies."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "
WITH genre_list AS (
  SELECT id, TRIM(genre) AS genre
  FROM movies, 
    (SELECT id, value AS genre
     FROM movies, 
       (SELECT id, genres FROM movies) AS mg,
       json_each(replace(replace(mg.genres, '|', '","'), ',', '","'))
    )
)
SELECT genre, COUNT(*) as num_movies
FROM (
  SELECT m.id, TRIM(g) AS genre
  FROM movies m, 
  (SELECT value AS g FROM json_each('[' || REPLACE(m.genres, '|', '","') || ']')) 
)
GROUP BY genre
ORDER BY num_movies DESC
LIMIT 1;
"
echo " "

echo "7. Вывести список из 10 последних зарегистрированных пользователей в формате \"Фамилия Имя|Дата регистрации\" (сначала фамилия, потом имя)."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "
SELECT name || '|' || register_date AS user_info
FROM users
ORDER BY register_date DESC
LIMIT 10;
"
echo " "

echo "8. С помощью рекурсивного CTE определить, на какие дни недели приходился ваш день рождения в каждом году."
echo --------------------------------------------------
sqlite3 movies_rating.db -box -echo "
WITH RECURSIVE years(y) AS (
  SELECT 1990
  UNION ALL
  SELECT y+1 FROM years WHERE y+1 <= 2025
)
SELECT y AS year, 
  strftime('%w', printf('%d-%02d-%02d', y, 4, 16)) AS weekday_num,
  CASE strftime('%w', printf('%d-%02d-%02d', y, 4, 16))
    WHEN '0' THEN 'Воскресенье'
    WHEN '1' THEN 'Понедельник'
    WHEN '2' THEN 'Вторник'
    WHEN '3' THEN 'Среда'
    WHEN '4' THEN 'Четверг'
    WHEN '5' THEN 'Пятница'
    WHEN '6' THEN 'Суббота'
  END AS weekday_name
FROM years;
"
echo " "