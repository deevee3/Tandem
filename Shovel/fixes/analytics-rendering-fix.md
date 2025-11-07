# Analytics Dashboard Rendering Fix

**Date**: November 6, 2025  
**Issue**: Analytics page was showing a spinning loader and not rendering data

## Root Causes

### 1. **Database Schema Mismatches**
- Used `first_response_at` instead of `first_responded_at` (actual column name)
- No `resolved_at` column exists - resolution times are tracked via `audit_events` table
- No `channel` column exists in conversations table
- No direct `queue_id` foreign key in conversations table

### 2. **SQL Syntax Incompatibility**
- Used MySQL-specific `TIMESTAMPDIFF()` function which doesn't exist in SQLite
- Needed SQLite-compatible date calculations using `julianday()` function

### 3. **Relationship Issues**
- Conversations don't have direct queue relationships
- Queue relationships exist through `queue_items` join table
- Agent assignments are tracked in `assignments` table

## Solutions Implemented

### 1. Fixed Column Names
```php
// BEFORE (wrong)
->whereNotNull('first_response_at')

// AFTER (correct)
->whereNotNull('first_responded_at')
```

### 2. Fixed Date Calculations for SQLite
```php
// BEFORE (MySQL only)
TIMESTAMPDIFF(SECOND, created_at, first_response_at)

// AFTER (SQLite compatible)
(julianday(first_responded_at) - julianday(created_at)) * 86400
```

### 3. Fixed Resolution Time Tracking
```php
// Resolution times come from audit_events table
DB::table('conversations')
    ->join('audit_events', function($join) {
        $join->on('conversations.id', '=', 'audit_events.conversation_id')
             ->where('audit_events.event_type', '=', 'conversation.resolved');
    })
    ->selectRaw('AVG((julianday(audit_events.occurred_at) - julianday(conversations.created_at)) * 86400) as avg_seconds')
```

### 4. Fixed Queue Relationships
```php
// Use queue_items join table
DB::table('conversations')
    ->join('queue_items', 'conversations.id', '=', 'queue_items.conversation_id')
    ->join('queues', 'queue_items.queue_id', '=', 'queues.id')
    ->select(
        'queues.id',
        'queues.name',
        DB::raw('COUNT(DISTINCT conversations.id) as total_conversations'),
        // ...
    )
```

### 5. Added Proper Type Casting
All numeric values are now explicitly cast to integers or floats to ensure proper JSON serialization:
```php
'total_conversations' => (int) $totalConversations,
'resolved_conversations' => (int) $resolvedConversations,
'resolution_rate' => round(..., 2),
```

### 6. Removed Non-Existent Data
Removed the "By Channel" breakdown card since the channel column doesn't exist in the database.

## Files Modified

1. **`app/Services/AnalyticsService.php`**
   - Fixed all SQL queries to use correct column names
   - Changed to SQLite-compatible date functions
   - Fixed queue and resolution relationships
   - Added proper type casting

2. **`resources/js/pages/admin/analytics/index.tsx`**
   - Removed channel breakdown from TypeScript types
   - Removed channel card from UI
   - Added type assertions for Object.entries

## Testing

Verified the fix works with:
```bash
php artisan tinker --execute="
\$service = app(\App\Services\AnalyticsService::class);
\$data = \$service->getDashboardMetrics();
echo 'Total conversations: ' . \$data['overview']['total_conversations'];
"
```

Result: ✅ Success! Data returned correctly.

## Database Schema Reference

### Key Tables
- **conversations**: Main conversation records
  - `first_responded_at` (not first_response_at)
  - No direct queue_id
  - No resolved_at column
  
- **queue_items**: Links conversations to queues
  - `conversation_id`
  - `queue_id`
  
- **assignments**: Links conversations to agents
  - `conversation_id`
  - `user_id`
  - `queue_id`
  - `accepted_at`
  
- **audit_events**: Event history
  - `conversation_id`
  - `event_type` (e.g., 'conversation.resolved')
  - `occurred_at`

## Result

✅ Analytics dashboard now loads successfully with:
- Total conversations and metrics
- Resolution rates
- SLA compliance
- Queue performance
- Agent performance
- Daily trends

The page displays data in responsive cards, tables, and charts.
