# Prints R's results for the cases in tests/ExprRTest.php and
# tests/Polars/PolarsExprRTest.php, whose expected values come from this
# output. Run from the repo root: Rscript tests/r/edge_cases.R
suppressPackageStartupMessages({ library(dplyr); library(stringr); library(lubridate) })
show <- function(label, expr) {
  out <- tryCatch(format(expr), error = function(e) paste("error:", conditionMessage(e)))
  cat(sprintf("%-28s %s\n", label, paste(out, collapse = " | ")))
}
cat("R", as.character(getRversion()), "dplyr", as.character(packageVersion("dplyr")),
    "stringr", as.character(packageVersion("stringr")), "lubridate", as.character(packageVersion("lubridate")), "\n")

show("row_number(x)", row_number(c(3, 1, 2)))
show("row_number(x, y)", row_number(c(3, 1, 2), c(1, 2, 3)))
show("ntile(v, 2)", ntile(c(5, 1, 3, 2), 2))
show("ntile(v)", ntile(c(5, 1, 3, 2)))

d <- as.Date(c("2024-01-01", "2024-02-01", NA))
t <- as.POSIXct(c("2024-01-01 10:00:00", "2024-02-01 00:00:00", NA), tz = "UTC")
show("d %in% 2024-02-01", d %in% as.Date("2024-02-01"))
show("d %in% c(2024-02-01, NA)", d %in% as.Date(c("2024-02-01", NA)))
show("t %in% 2024-01-01 10:00", t %in% as.POSIXct("2024-01-01 10:00:00", tz = "UTC"))
show("t %in% 2024-02-01", t %in% as.POSIXct("2024-02-01", tz = "UTC"))
show("t == 2024-02-01", t == as.POSIXct("2024-02-01", tz = "UTC"))

show("coalesce(NaN)", coalesce(c(NaN, 1, NA), c(5, 6, 7)))

show("str_pad pad = é", str_pad("a", 4, pad = "é"))
show("str_pad pad = ab", str_pad("a", 4, pad = "ab"))
show("str_pad pad = ''", str_pad("a", 4, pad = ""))
show("str_pad pad = 漢", str_pad("a", 4, pad = "漢"))

x <- c("03-15", "02-29", "10:30", "13-01", NA)
show("parse md", format(suppressWarnings(parse_date_time(x, "md")), "%Y-%m-%d %H:%M:%S"))
show("parse HM", format(suppressWarnings(parse_date_time(x, "HM")), "%Y-%m-%d %H:%M:%S"))
show("parse d", format(suppressWarnings(parse_date_time("15", "d")), "%Y-%m-%d"))

t0 <- ymd_hms("2024-01-31 00:00:00")
d0 <- ymd("2024-01-31")
show("t + seconds(1.5)", format(t0 + seconds(1.5), "%Y-%m-%d %H:%M:%OS3"))
show("t + 2 * days(1)", t0 + 2 * days(1))
show("t + days(2.0)", t0 + days(2.0))
for (f in c("minutes", "hours", "days", "weeks", "months", "years")) show(paste0(f, "(1.5)"), get(f)(1.5))
show("2.5 * days(1)", 2.5 * days(1))
show("d + hours(1)", format(d0 + hours(1), "%Y-%m-%d %H:%M:%S"))
show("d + seconds(1.5)", format(d0 + seconds(1.5), "%Y-%m-%d %H:%M:%OS3"))
show("d - minutes(30)", format(d0 - minutes(30), "%Y-%m-%d %H:%M:%S"))
show("class(d + days(1))", class(d0 + days(1)))
