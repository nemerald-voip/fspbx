-- Artificial diagnostic load, never installed as a startup script.
local mode, output, rate, seconds = argv[1], argv[2], tonumber(argv[3]), tonumber(argv[4])
assert(mode == 'uuid_getvar' or mode == 'eval')
assert(rate and rate > 0 and rate <= 20000)
assert(seconds and seconds > 0 and seconds <= 600)
local api = freeswitch.API()
local queries = {}
for i = 5, #argv do
    local uuid = argv[i]
    assert(uuid:match('^[%da-f%-]+$') and #uuid == 36)
    queries[#queries + 1] = mode == 'eval'
        and ('uuid:' .. uuid .. ' ${variable_memory_lab_uuid}')
        or (uuid .. ' memory_lab_uuid')
end
assert(#queries > 0)
local function now() return tonumber(api:getTime()) end
local start, count, errors, max_batch_ms = now(), 0, 0, 0
local batch = math.max(1, math.floor(rate / 10))
local target = math.floor(rate * seconds)
while count < target do
    local batch_start = now()
    for _ = 1, math.min(batch, target - count) do
        local value = api:execute(mode, queries[(count % #queries) + 1])
        if not value or #value ~= 36 then errors = errors + 1 end
        count = count + 1
    end
    max_batch_ms = math.max(max_batch_ms, now() - batch_start)
    local delay = start + count * 1000 / rate - now()
    if delay > 0 then freeswitch.msleep(math.floor(delay)) end
    if now() - start > (seconds + 60) * 1000 then break end
end
local file = assert(io.open(output, 'w'))
file:write(string.format('{"mode":"%s","count":%d,"errors":%d,"elapsed_ms":%d,"max_batch_ms":%d}\n',
    mode, count, errors, now() - start, max_batch_ms))
file:close()
