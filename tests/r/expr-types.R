# Writes tests/fixtures/expr/types.json: the column type dplyr gives each
# aggregate when every group is NA. Run from the repo root: Rscript tests/r/expr-types.R
suppressPackageStartupMessages({ library(dplyr); library(jsonlite) })

df <- tibble(
  g = c("a", "b", "a", "b"),
  x = NA_real_,
  i = NA_integer_,
  b = NA,
  s = NA_character_,
  d = as.Date(NA)
)

type <- function(...) vctrs::vec_ptype_abbr(pull(df %>% group_by(g) %>% summarise(...), out))

cases <- list(
  sum_dbl = type(out = sum(x)),
  sum_int = type(out = sum(i)),
  sum_lgl = type(out = sum(b)),
  mean_dbl = type(out = mean(x)),
  mean_int = type(out = mean(i)),
  median_int = type(out = median(i)),
  sd_int = type(out = sd(i)),
  var_dbl = type(out = var(x)),
  quantile_int = type(out = quantile(i, 0.5, na.rm = TRUE)),
  iqr_int = type(out = IQR(i, na.rm = TRUE)),
  min_int = type(out = min(i)),
  max_dbl = type(out = max(x)),
  min_lgl = type(out = min(b)),
  median_lgl = type(out = median(b)),
  first_lgl = type(out = first(b)),
  min_chr = type(out = min(s)),
  max_date = type(out = max(d)),
  first_int = type(out = first(i)),
  last_chr = type(out = last(s)),
  nth_dbl = type(out = nth(x, 1)),
  any_lgl = type(out = any(b)),
  all_lgl = type(out = all(b))
)
write(toJSON(cases, auto_unbox = TRUE, pretty = TRUE), "tests/fixtures/expr/types.json")
