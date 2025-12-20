import type { SidebarsConfig } from "@docusaurus/plugin-content-docs";

const sidebar: SidebarsConfig = {
  apisidebar: [
    {
      type: "doc",
      id: "api/v1/fs-pbx-api",
    },
    {
      type: "category",
      label: "Domains",
      items: [
        {
          type: "doc",
          id: "api/v1/list-domains",
          label: "List domains",
          className: "api-method get",
        },
      ],
    },
    {
      type: "category",
      label: "Endpoints",
      items: [
        {
          type: "doc",
          id: "api/v1/p-os-tapiv-1-domains-middleware-should-enforce-domains-create-permissionin-user-home-domain-context",
          label: "POST /api/v1/domains\nMiddleware should enforce domains_create permission (in user home-domain context).",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/v1/g-e-tapiv-1-domainsdomain-uuid-middleware-should-already-enforce-domain-accessif-route-hasdomain-uuid-domains-view-permission",
          label: "GET /api/v1/domains/{domain_uuid}\nMiddleware should already enforce:\n - domain access (if route has {domain_uuid})\n - domains_view permission",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/v1/p-u-tapiv-1-domainsdomain-uuid-middleware-should-enforce-domain-access-domains-update-permission",
          label: "PUT /api/v1/domains/{domain_uuid}\nMiddleware should enforce:\n - domain access\n - domains_update permission",
          className: "api-method put",
        },
        {
          type: "doc",
          id: "api/v1/d-elet-eapiv-1-domainsdomain-uuid-middleware-should-enforce-domain-access-domains-delete-permission",
          label: "DELETE /api/v1/domains/{domain_uuid}\nMiddleware should enforce:\n - domain access\n - domains_delete permission",
          className: "api-method delete",
        },
      ],
    },
  ],
};

export default sidebar.apisidebar;
