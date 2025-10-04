-- /usr/local/etc/haproxy/xlsx_block.lua
-- Prevent Collabora from loading huge XLSX files (> 10 MB)

core.register_action("block_xlsx", { "http-req" }, function(txn)
  local path = txn.sf:path()
  if path and path:match("%.xlsx$") then
    txn.http:res_set_status(403, "Forbidden")
    txn.http:res_add_header("content-type", "text/plain")
    txn.http:res_set_body("Access to large XLSX files is disabled.")
    txn:Done()
  end
end)
