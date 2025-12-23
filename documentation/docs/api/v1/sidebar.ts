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
        {
          type: "doc",
          id: "api/v1/create-a-domain",
          label: "Create a domain",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/v1/retrieve-a-domain",
          label: "Retrieve a domain",
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
