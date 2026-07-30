---
name: data-analysis
description: "Analyse data from spreadsheets, CSV exports, or WordPress content to find patterns, summarise metrics, or answer questions about numbers. Use when the user uploads a data file and asks for insights, wants a summary of spreadsheet contents, asks to compare figures, find averages or totals, identify outliers, or build a report from raw data. Also trigger when the user asks to analyse WooCommerce orders, form submissions, or any tabular data. Do NOT trigger for pure document reading with no numeric analysis."
---

# Data Analysis Skill

## Available Tools

| Tool | When to use |
|---|---|
| `read_spreadsheet` | Read rows from an xlsx, xlsm, csv, or tsv file (up to 2000 rows). |
| `analyze_spreadsheet` | Get column-level statistics: min, max, avg, sum, unique count, blank count. |
| `create_spreadsheet` | Write analysis results or a summary table to a new xlsx file. |
| `search_content` | Find WordPress posts or pages whose content contains specific data. |
| `fetch_url` | Pull in a CSV or JSON data file from an external URL. |

## Workflows

### Summarise an uploaded spreadsheet

1. Call `read_spreadsheet` with the file path. Review `headers` and the first few rows.
2. Call `analyze_spreadsheet` on the same file to get column statistics.
3. Identify the most meaningful columns (numeric columns with variance, key categorical columns).
4. Summarise: total rows, key averages/totals, notable outliers (values far from the mean).
5. Offer to export the summary as a new xlsx with `create_spreadsheet`.

### Answer a specific question about the data

1. Call `read_spreadsheet` — scan `headers` to identify the relevant columns.
2. If the question is about averages, totals, or distributions: call `analyze_spreadsheet`.
3. If the question requires row-by-row inspection (e.g. "which rows have X > 100"): read the data and filter in your response.
4. Quote specific values from the data; never estimate or guess figures.

### Build a summary report

1. Analyse the source file with `read_spreadsheet` + `analyze_spreadsheet`.
2. Structure the findings as sections (overview, key metrics, breakdown by category).
3. Call `create_spreadsheet` to write the summary table if the user wants a file output.
4. Alternatively, export to `html_to_docx` for a Word report.

### Analyse WooCommerce or form data

1. Use the relevant WooCommerce or form tools to export data (e.g. `wc_search_products`, `list_forms`).
2. If the data comes back as JSON arrays, treat each field as a column and analyse numerically.
3. Highlight totals, averages, and any outlier values.

## Quality Rules

- **Never invent numbers.** Only report values that appear in the data. If a calculation is approximate, say so.
- **Always state the row count** — the user needs to know how much data was analysed.
- **Flag truncation.** `read_spreadsheet` caps at 2000 rows; if `truncated: true`, note that analysis covers a sample.
- **Use `analyze_spreadsheet` for numeric columns**, not manual counting from `read_spreadsheet` rows.
- **Group categorical data** — for text columns with few unique values (e.g. status, category), include a frequency breakdown.
- **Offer a file output** when the analysis is more than 5 rows — a spreadsheet is easier to share than a chat response.
