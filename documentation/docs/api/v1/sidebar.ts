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
        {
          type: "doc",
          id: "api/v1/delete-a-domain",
          label: "Delete a domain",
          className: "api-method delete",
        },
        {
          type: "doc",
          id: "api/v1/update-a-domain",
          label: "Update a domain",
          className: "api-method patch",
        },
      ],
    },
  ],
};

export default sidebar.apisidebar;
