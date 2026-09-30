"""Run Lua tests with the system Lua shared library, without installing a CLI."""
import ctypes
import ctypes.util
import pathlib
import sqlite3
import sys
import xml.etree.ElementTree as ET

library = ctypes.util.find_library("lua5.2")
if not library:
    sys.exit("Lua 5.2 shared library is required (or run the Lua fixture with lua).")
lua = ctypes.CDLL(library)
lua.luaL_newstate.restype = ctypes.c_void_p
lua.luaL_openlibs.argtypes = [ctypes.c_void_p]
lua.luaL_loadfilex.argtypes = [ctypes.c_void_p, ctypes.c_char_p, ctypes.c_char_p]
lua.lua_pcallk.argtypes = [ctypes.c_void_p, ctypes.c_int, ctypes.c_int, ctypes.c_int, ctypes.c_int, ctypes.c_void_p]
lua.lua_tolstring.argtypes = [ctypes.c_void_p, ctypes.c_int, ctypes.c_void_p]
lua.lua_tolstring.restype = ctypes.c_char_p
lua.lua_close.argtypes = [ctypes.c_void_p]
lua.lua_getglobal.argtypes = [ctypes.c_void_p, ctypes.c_char_p]
state = lua.luaL_newstate()
try:
    lua.luaL_openlibs(state)
    for name in ("agent_call_tracking_test.lua", "directory_test.lua"):
        fixture = pathlib.Path(__file__).with_name(name)
        result = lua.luaL_loadfilex(state, str(fixture).encode(), None)
        if not result:
            result = lua.lua_pcallk(state, 0, 0, 0, 0, None)
        if result:
            sys.exit(lua.lua_tolstring(state, -1, None).decode())
    lua.lua_getglobal(state, b"DIRECTORY_XML_FIXTURES")
    documents = ET.fromstring(lua.lua_tolstring(state, -1, None).decode())
    assert len(documents) > 0
    print(f"PASS: parsed {len(documents)} generated XML documents")
    # Execute the actual generated owner query, including orphan and tenant cases.
    lua.lua_getglobal(state, b"DIRECTORY_OWNER_SQL")
    owner_sql = lua.lua_tolstring(state, -1, None).decode()
    with sqlite3.connect(":memory:") as database:
        database.executescript("""
            CREATE TABLE v_extension_users (domain_uuid TEXT, extension_uuid TEXT, user_uuid TEXT);
            CREATE TABLE v_users (domain_uuid TEXT, user_uuid TEXT, contact_uuid TEXT);
            INSERT INTO v_extension_users VALUES ('account-a', 'extension-a', 'orphan');
            INSERT INTO v_extension_users VALUES ('account-a', 'extension-a', 'owner');
            INSERT INTO v_users VALUES ('account-b', 'orphan', 'foreign-contact');
            INSERT INTO v_users VALUES ('account-b', 'owner', 'foreign-contact');
            INSERT INTO v_users VALUES ('account-a', 'owner', 'own-contact');
        """)
        parameters = {"domain_uuid": "account-a", "extension_uuid": "extension-a"}
        assert database.execute(owner_sql, parameters).fetchone() == ("orphan", None)
        database.execute("DELETE FROM v_extension_users WHERE user_uuid = 'orphan'")
        assert database.execute(owner_sql, parameters).fetchone() == ("owner", "own-contact")
        database.execute("DELETE FROM v_extension_users")
        assert database.execute(owner_sql, parameters).fetchone() is None
    print("PASS: owner query preserves first assignment and tenant isolation")
finally:
    lua.lua_close(state)
