```{.text .output}
SELECT "species", "n", "mass" FROM (SELECT "species", "n", "mass", ROW_NUMBER() OVER (ORDER BY ("species" IS NULL), "species") AS "__phibble_row" FROM (SELECT "species" AS "species", COUNT(*) AS "n", (CASE WHEN COUNT("body_mass") = 0 THEN NULL ELSE AVG(CAST("body_mass" AS REAL)) END) AS "mass" FROM (SELECT * FROM (SELECT *, rowid AS "__phibble_row" FROM "penguins") AS q1 WHERE ("year" = ?)) AS q2 GROUP BY "species") AS q3) AS q4 ORDER BY ("__phibble_row" IS NULL), "__phibble_row"
```
