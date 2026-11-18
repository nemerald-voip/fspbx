#define _GNU_SOURCE
#include <lua.h>
#include <lauxlib.h>
#include <malloc.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

/* Lab only: statistics or an explicit trim. Never changes allocator settings. */
int luaopen_memory_lab(lua_State *L)
{
    const char *mode = luaL_checkstring(L, 1);
    if (!strcmp(mode, "trim")) {
        lua_pushinteger(L, malloc_trim(0));
        return 1;
    }
    if (strcmp(mode, "stats")) return luaL_error(L, "expected stats or trim");
    struct mallinfo2 m = mallinfo2();
    char *xml = NULL;
    size_t size = 0;
    FILE *out = open_memstream(&xml, &size);
    if (!out) return luaL_error(L, "open_memstream failed");
    int result = malloc_info(0, out);
    fclose(out);
    if (result) { free(xml); return luaL_error(L, "malloc_info failed"); }
    unsigned heaps = 0;
    for (char *p = xml; (p = strstr(p, "<heap nr=")); p++) heaps++;
    free(xml);
    char json[512];
    snprintf(json, sizeof(json),
        "{\"arena\":%zu,\"uordblks\":%zu,\"fordblks\":%zu,"
        "\"hblkhd\":%zu,\"keepcost\":%zu,\"heaps\":%u}",
        m.arena, m.uordblks, m.fordblks, m.hblkhd, m.keepcost, heaps);
    lua_pushstring(L, json);
    return 1;
}
