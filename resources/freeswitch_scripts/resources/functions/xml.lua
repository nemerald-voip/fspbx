local xml = {}

function xml:new(o)
    o = o or {}
    setmetatable(o, self);
    self.__index = self;
    self.xml = {};
    return o;
end

function xml:append(data)
    table.insert(self.xml, data);
end

function xml:build()
    return table.concat(self.xml, "\n");
end

-- Escape a raw XML attribute without changing FreeSWITCH ${...} expressions.
function xml.escape(s)
    return (string.gsub(tostring(s or ""), "[&\"><']", {
        ["&"] = "&amp;",
        ["<"] = "&lt;",
        [">"] = "&gt;",
        ['"'] = "&quot;",
        ["'"] = "&apos;"
    }))
end

function xml.sanitize(s)
    -- Keep the existing dollar-removal contract for current callers.
    return xml.escape((tostring(s or ""):gsub("%$", "")))
end

return xml;
