```{.text .output}
SELECT "origin", "month", "precip", "running", "of_wettest" FROM (SELECT "origin", "month", "precip", "running", (CASE WHEN (CASE WHEN COUNT("precip") OVER (PARTITION BY "origin") = 0 THEN -9e999 ELSE MAX("precip") OVER (PARTITION BY "origin") END) = 0 THEN (CASE WHEN "precip" > 0 THEN 9e999 WHEN "precip" < 0 THEN -9e999 WHEN "precip" = 0 THEN NULL END) ELSE CAST("precip" AS REAL) / (CASE WHEN COUNT("precip") OVER (PARTITION BY "origin") = 0 THEN -9e999 ELSE MAX("precip") OVER (PARTITION BY "origin") END) END) AS "of_wettest", "__phibble_row" FROM (SELECT "origin", "month", "precip", (CASE WHEN COUNT(*) OVER (PARTITION BY "origin" ORDER BY ("origin" IS NULL), "origin", ("month" IS NULL), "month", ("__phibble_row" IS NULL), "__phibble_row" ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) > COUNT("precip") OVER (PARTITION BY "origin" ORDER BY ("origin" IS NULL), "origin", ("month" IS NULL), "month", ("__phibble_row" IS NULL), "__phibble_row" ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) THEN NULL ELSE SUM("precip") OVER (PARTITION BY "origin" ORDER BY ("origin" IS NULL), "origin", ("month" IS NULL), "month", ("__phibble_row" IS NULL), "__phibble_row" ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) END) AS "running", "__phibble_row" FROM (SELECT "origin", "month", "precip", ROW_NUMBER() OVER (ORDER BY ("origin" IS NULL), "origin", ("month" IS NULL), "month") AS "__phibble_row" FROM (SELECT "origin" AS "origin", "month" AS "month", (CASE WHEN COUNT(*) > COUNT("precip") THEN NULL ELSE COALESCE(SUM("precip"), 0) END) AS "precip" FROM (SELECT * FROM (SELECT *, rowid AS "__phibble_row" FROM "weather") AS q1 WHERE ("month" BETWEEN ? AND ?)) AS q2 GROUP BY "origin", "month") AS q3) AS q4) AS q5) AS q6 ORDER BY ("origin" IS NULL), "origin", ("month" IS NULL), "month", ("__phibble_row" IS NULL), "__phibble_row"

# A tibble: 9 × 5
# Groups:   origin [3]
  origin month precip running of_wettest
  <chr>  <int>  <dbl>   <dbl>      <dbl>
1 EWR        6   8.73    8.73      1    
2 EWR        7   3.74   12.5       0.428
3 EWR        8   4.57   17.0       0.523
4 JFK        6   7.95    7.95      1    
5 JFK        7   2.26   10.2       0.284
6 JFK        8   2.73   12.9       0.343
7 LGA        6   8.16    8.16      1    
8 LGA        7   2.8    11.0       0.343
9 LGA        8   1.97   12.9       0.241
```
