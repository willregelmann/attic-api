# AI Curator Architecture

## Overview

The AI Curator system allows collections to be automatically maintained by AI agents that can suggest additions, removals, and reorganizations based on configurable rules and preferences.

## Architecture Decision

We chose a **hybrid architecture** with the core system in Laravel and background processing via queue workers:

### Why Not a Separate Microservice (Yet)?

1. **Simplicity**: For a hobby project, keeping everything in one codebase is easier to maintain
2. **Shared Models**: Direct access to Laravel models and database
3. **Queue Isolation**: Laravel's queue system provides sufficient isolation
4. **Easy Scaling**: Can move to separate service later if needed

### Current Architecture

```
Web Requests → Laravel API → Queue Job → Curator Service → AI API
                    ↓             ↓              ↓
                PostgreSQL   Redis Queue    OpenAI/Claude
```

## Database Schema

### Tables

1. **collection_curators**: Stores curator configurations
   - Personality and behavior rules
   - Schedule settings
   - Auto-approval thresholds
   - Performance metrics

2. **curator_suggestions**: Individual suggestions made by curators
   - Action type (add/remove/reorder)
   - Confidence scores
   - Review status and notes
   - Execution tracking

3. **curator_run_logs**: Audit trail of curator executions
   - Run status and timing
   - API usage metrics
   - Error tracking

## Key Components

### 1. CuratorService (`app/Services/CuratorService.php`)

Core service handling:
- AI prompt generation
- Suggestion creation
- API communication
- Suggestion validation

### 2. ProcessCuratorRun Job (`app/Jobs/ProcessCuratorRun.php`)

Background job that:
- Executes curator logic
- Handles retries
- Updates metrics
- Logs failures

### 3. RunScheduledCurators Command (`app/Console/Commands/RunScheduledCurators.php`)

Scheduled command that:
- Checks for due curators
- Queues curator runs
- Updates schedules

### 4. GraphQL Mutations (`app/GraphQL/Mutations/CuratorMutations.php`)

API endpoints for:
- Creating/updating curators
- Reviewing suggestions
- Manual curator runs
- Bulk operations

## Configuration

### Curator Config Structure

```json
{
  "personality": "A knowledgeable Pokemon card expert",
  "rules": [
    "Only include cards from Standard format",
    "Maximum 2 copies of any card"
  ],
  "preferences": {
    "rarity_weight": 0.3,
    "meta_relevance_weight": 0.7,
    "price_ceiling": 50.00
  },
  "search_queries": [
    "Pokemon TCG tournament winners"
  ],
  "ai_model": "claude-3-opus-20240229",
  "temperature": 0.7
}
```

### Environment Variables

The system supports both Anthropic Claude and OpenAI GPT models.

#### Option 1: Anthropic Claude (Recommended)

```env
# Choose AI provider
AI_PROVIDER=anthropic

# Anthropic API settings
ANTHROPIC_API_KEY=your-anthropic-api-key
ANTHROPIC_MODEL=claude-3-opus-20240229

# Available Claude models:
# - claude-3-opus-20240229 (Most capable, best for complex curation)
# - claude-3-sonnet-20240229 (Balanced performance/cost)
# - claude-3-haiku-20240307 (Fastest, lowest cost)
```

#### Option 2: OpenAI GPT

```env
# Choose AI provider
AI_PROVIDER=openai

# OpenAI API settings
OPENAI_API_KEY=your-openai-api-key
OPENAI_MODEL=gpt-4

# Available models:
# - gpt-4 (Most capable)
# - gpt-4-turbo (Faster, cheaper)
# - gpt-3.5-turbo (Fastest, cheapest)
```

### Why Anthropic Claude?

Claude is particularly well-suited for collection curation because:
- **Better instruction following**: More reliable at following complex curation rules
- **Thoughtful analysis**: Provides nuanced reasoning for suggestions
- **Safety-first**: Less likely to suggest inappropriate content
- **JSON handling**: Reliable structured output without special formatting modes

## Workflow

### 1. Curator Creation
```
User → Create Curator → Set Rules/Personality → Configure Schedule
```

### 2. Suggestion Generation
```
Scheduled/Manual Trigger → Queue Job → Call AI API → Parse Response → Create Suggestions
```

### 3. Review Process
```
Suggestions Created → Notification (optional) → Human Review → Approve/Reject → Execute
```

### 4. Auto-Approval Flow
```
High Confidence Suggestion → Auto-Approve → Queue Execution → Update Collection
```

## Queue Configuration

### Running Queue Workers

```bash
# Development
php artisan queue:work --queue=curators,default

# Production (with supervisor)
php artisan queue:work --queue=curators --tries=3 --timeout=300
```

### Supervisor Configuration (Production)

```ini
[program:curator-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=curators --tries=3 --timeout=300
autostart=true
autorestart=true
numprocs=2
```

## Scheduling

The system runs scheduled curators every 15 minutes:

```php
// routes/console.php
Schedule::command('curators:run-scheduled')->everyFifteenMinutes();
```

For Railway deployment, add to your start script:
```bash
# Run Laravel scheduler in background
while true; do php artisan schedule:run >> /dev/null 2>&1; sleep 60; done &
```

## API Usage Examples

### Create a Curator

```graphql
mutation CreateCurator {
  createCurator(
    collection_id: "uuid-here"
    name: "Pokemon Meta Curator"
    description: "Maintains competitive Pokemon cards"
    curator_config: {
      personality: "Expert in Pokemon TCG competitive play"
      rules: ["Focus on tournament-legal cards", "Max budget $500"]
      ai_model: "gpt-4"
      temperature: 0.7
    }
    schedule_type: "daily"
    auto_approve: false
    confidence_threshold: 85
  ) {
    id
    name
    status
  }
}
```

### Review Suggestions

```graphql
mutation ReviewSuggestion {
  reviewSuggestion(
    id: "suggestion-uuid"
    action: "approve"
    notes: "Good suggestion"
    execute_now: true
  ) {
    id
    status
    executed
  }
}
```

### Manual Curator Run

```graphql
mutation RunCurator {
  runCurator(id: "curator-uuid") {
    success
    message
  }
}
```

## Security Considerations

1. **API Key Protection**: Store OpenAI keys in environment variables only
2. **Permission Checks**: Only collection maintainers can manage curators
3. **Rate Limiting**: Implement API rate limiting to prevent abuse
4. **Cost Controls**: Monitor API usage and set limits
5. **Confidence Thresholds**: Require human review for low-confidence suggestions

## Performance Optimization

1. **Queue Priority**: Use separate queue for curator jobs
2. **Caching**: Cache collection data during runs
3. **Batch Processing**: Process multiple suggestions in single API calls
4. **Timeout Settings**: Set appropriate timeouts for API calls

## Monitoring

### Key Metrics to Track

- Curator run frequency and duration
- Suggestion approval rates
- API costs per curator
- Error rates and types
- Queue depth and processing time

### Logging

```php
// Curator runs logged to:
storage/logs/curator-runs.log

// Failed jobs tracked in:
failed_jobs table
```

## Future Enhancements

1. **Multiple AI Providers**: Support for Claude, Gemini, local models
2. **Webhook Notifications**: Alert maintainers of new suggestions
3. **Learning System**: Curators learn from approval/rejection patterns
4. **Collaborative Filtering**: Curators learn from similar collections
5. **Cost Optimization**: Intelligent caching and batching
6. **Separate Microservice**: Move to dedicated Python/Node service for scale

## Testing

### Unit Tests
```bash
php artisan test --filter CuratorTest
```

### Manual Testing
1. Create test collection
2. Configure curator with low auto-approve threshold
3. Run curator manually: `php artisan curators:run-scheduled`
4. Check suggestions in database
5. Review via GraphQL mutations

## Troubleshooting

### Common Issues

1. **No suggestions generated**
   - Check OpenAI API key is set
   - Verify curator config is valid JSON
   - Check logs for API errors

2. **Jobs not processing**
   - Ensure queue worker is running
   - Check failed_jobs table
   - Verify Redis connection

3. **Scheduled runs not happening**
   - Check scheduler is running (`cron` or `while` loop)
   - Verify curator status is 'active'
   - Check next_run_at timestamp

## Cost Estimation

### Anthropic Claude API Costs
**Claude 3 Opus** (Best quality):
- Input: $15 per million tokens (~$0.015 per 1K)
- Output: $75 per million tokens (~$0.075 per 1K)
- Average curator run: ~2K tokens = ~$0.09

**Claude 3 Sonnet** (Balanced):
- Input: $3 per million tokens (~$0.003 per 1K)
- Output: $15 per million tokens (~$0.015 per 1K)
- Average curator run: ~2K tokens = ~$0.018

**Claude 3 Haiku** (Budget):
- Input: $0.25 per million tokens (~$0.00025 per 1K)
- Output: $1.25 per million tokens (~$0.00125 per 1K)
- Average curator run: ~2K tokens = ~$0.0015

### OpenAI API Costs
**GPT-4**:
- Input: ~$0.03 per 1K tokens
- Output: ~$0.06 per 1K tokens
- Average curator run: ~2K tokens = ~$0.12

**GPT-3.5-turbo** (Budget):
- Input: ~$0.001 per 1K tokens
- Output: ~$0.002 per 1K tokens
- Average curator run: ~2K tokens = ~$0.004

### Monthly Cost Comparison
For 10 collections with daily runs (300 runs/month):
- **Claude 3 Opus**: ~$27/month
- **Claude 3 Sonnet**: ~$5.40/month (Recommended for most use cases)
- **Claude 3 Haiku**: ~$0.45/month
- **GPT-4**: ~$36/month
- **GPT-3.5-turbo**: ~$1.20/month

**Recommendation**: Start with Claude 3 Sonnet for the best balance of quality and cost.