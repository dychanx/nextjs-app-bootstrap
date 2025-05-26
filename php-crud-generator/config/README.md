This directory stores JSON configuration files for each table's CRUD page.

Each JSON file is named {table_name}.json and contains settings like:

{
  "visible_columns": ["COL1", "COL2", ...],
  "editable_columns": ["COL1", "COL3", ...]
}

These settings control which columns are shown in the list and which are editable in forms.
