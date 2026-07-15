# Agent Behavior Rules for POLAR Workspace

## Production Database Safety Constraints
- **CRITICAL RULE**: The agent is STRICTLY PROHIBITED from executing any query that modifies data or structure in the production database (e.g. `INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `REPLACE`, `CREATE`, `TRUNCATE`, etc.).
- **READ-ONLY ONLY**: The agent must ONLY execute read-only queries (e.g. `SELECT`, `SHOW`, `DESCRIBE`, `EXPLAIN`) when auditing or validating information in the production systems or tenants databases.
