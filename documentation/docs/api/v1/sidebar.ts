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
          id: "api/v1/update-a-domain",
          label: "Update a domain",
          className: "api-method patch",
        },
        {
          type: "doc",
          id: "api/v1/delete-a-domain",
          label: "Delete a domain",
          className: "api-method delete",
        },
      ],
    },
    {
      type: "category",
      label: "Extensions",
      items: [
        {
          type: "doc",
          id: "api/v1/list-extensions",
          label: "List extensions",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/v1/create-an-extension",
          label: "Create an extension",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/v1/retrieve-an-extension",
          label: "Retrieve an extension",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/v1/update-an-extension",
          label: "Update an extension",
          className: "api-method patch",
        },
        {
          type: "doc",
          id: "api/v1/delete-an-extension",
          label: "Delete an extension",
          className: "api-method delete",
        },
      ],
    },
    {
      type: "category",
      label: "Ring Groups",
      items: [
        {
          type: "doc",
          id: "api/v1/list-ring-groups",
          label: "List ring groups",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/v1/create-a-ring-group",
          label: "Create a ring group",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/v1/retrieve-a-ring-group",
          label: "Retrieve a ring group",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/v1/update-a-ring-group",
          label: "Update a ring group",
          className: "api-method patch",
        },
        {
          type: "doc",
          id: "api/v1/delete-a-ring-group",
          label: "Delete a ring group",
          className: "api-method delete",
        },
      ],
    },
    {
      type: "category",
      label: "Voicemails",
      items: [
        {
          type: "doc",
          id: "api/v1/list-voicemails",
          label: "List voicemails",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/v1/create-a-voicemail",
          label: "Create a voicemail",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/v1/retrieve-a-voicemail",
          label: "Retrieve a voicemail",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/v1/update-a-voicemail",
          label: "Update a voicemail",
          className: "api-method patch",
        },
        {
          type: "doc",
          id: "api/v1/delete-a-voicemail",
          label: "Delete a voicemail",
          className: "api-method delete",
        },
      ],
    },
  ],
};

export default sidebar.apisidebar;
