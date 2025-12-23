{
  "retellai_api_documentation": [
    {
      "endpoint_path": "POST /v2/create-phone-call",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/create-phone-call",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nconst phoneCallResponse = await client.call.createPhoneCall({\n  from_number: '+14157774444',\n  to_number: '+12137774445',\n});\n\nconsole.log(phoneCallResponse.agent_id);",
      "request_example_citation": "https://docs.retellai.com/api-references/create-phone-call",
      "response_example": "{\n  \"call_type\": \"phone_call\",\n  \"from_number\": \"+12137771234\",\n  \"to_number\": \"+12137771235\",\n  \"direction\": \"inbound\",\n  \"call_id\": \"Jabr9TXYYJHfvl6Syypi88rdAHYHmcq6\",\n  \"agent_id\": \"oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD\",\n  \"agent_version\": 1,\n  \"call_status\": \"registered\",\n  \"telephony_identifier\": {\n    \"twilio_call_sid\": \"CA5d0d0d8047bf685c3f0ff980fe62c123\"\n  },\n  \"agent_name\": \"My Agent\",\n  \"metadata\": {},\n  \"retell_llm_dynamic_variables\": {\n    \"customer_name\": \"John Doe\"\n  },\n  \"collected_dynamic_variables\": {\n    \"last_node_name\": \"Test node\"\n  },\n  \"custom_sip_headers\": {\n    \"X-Custom-Header\": \"Custom Value\"\n  },\n  \"data_storage_setting\": \"everything\",\n  \"opt_in_signed_url\": true,\n  \"start_timestamp\": 1703302407333,\n  \"end_timestamp\": 1703302428855,\n  \"duration_ms\": 10000,\n  \"transcript\": \"Agent: hi how are you doing?\\nUser: Doing pretty well. How are you?\\nAgent: That's great to hear! I'm doing well too, thanks! What's up?\\nUser: I don't have anything in particular.\\nAgent: Got it, just checking in!\\nUser: Alright. See you.\\nAgent: have a nice day\",\n  \"recording_url\": \"https://retellai.s3.us-west-2.amazonaws.com/Jabr9TXYYJHfvl6Syypi88rdAHYHmcq6/recording.wav\",\n  \"disconnection_reason\": \"agent_hangup\",\n  \"call_analysis\": {\n    \"call_summary\": \"The agent called the user to ask question about his purchase inquiry.\",\n    \"user_sentiment\": \"Positive\",\n    \"call_successful\": true\n  }\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/create-phone-call",
      "sdk_installation_instructions": "import Retell from 'retell-sdk';",
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/create-phone-call"
    },
    {
      "endpoint_path": "POST /v2/create-web-call",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/create-web-call",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nconst webCallResponse = await client.call.createWebCall({ agent_id: 'oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD' });\n\nconsole.log(webCallResponse.agent_id);",
      "request_example_citation": "https://docs.retellai.com/api-references/create-web-call",
      "response_example": "{\n  \"call_type\": \"web_call\",\n  \"access_token\": \"eyJhbGciOiJIUzI1NiJ9.eyJ2aWRlbyI6eyJyb29tSm9p\",\n  \"call_id\": \"Jabr9TXYYJHfvl6Syypi88rdAHYHmcq6\",\n  \"agent_id\": \"oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD\",\n  \"agent_version\": 1,\n  \"call_status\": \"registered\",\n  \"agent_name\": \"My Agent\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/create-web-call",
      "sdk_installation_instructions": "import Retell from 'retell-sdk';",
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/create-web-call"
    },
    {
      "endpoint_path": "GET /v2/get-call/{call_id}",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/get-call",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nconst callResponse = await client.call.retrieve('119c3f8e47135a29e65947eeb34cf12d');\n\nconsole.log(callResponse);",
      "request_example_citation": "https://docs.retellai.com/api-references/get-call",
      "response_example": "{\n  \"call_type\": \"web_call\",\n  \"access_token\": \"eyJhbGciOiJIUzI1NiJ9.eyJ2aWRlbyI6eyJyb29tSm9p\",\n  \"call_id\": \"Jabr9TXYYJHfvl6Syypi88rdAHYHmcq6\",\n  \"agent_id\": \"oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD\",\n  \"agent_version\": 1,\n  \"call_status\": \"registered\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/get-call",
      "sdk_installation_instructions": "import Retell from 'retell-sdk';",
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/get-call"
    },
    {
      "endpoint_path": "POST /v2/list-calls",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/list-calls",
      "request_example": "{\n  \"filter_criteria\": {\n    \"agent_id\": [\"agent_oBeDLoLOeuAbiuaMFXRtDOLriT12345\"],\n    \"call_status\": [\"ended\"],\n    \"call_type\": [\"phone_call\"]\n  },\n  \"sort_order\": \"descending\",\n  \"limit\": 50\n}",
      "request_example_citation": "https://docs.retellai.com/api-references/list-calls",
      "response_example": "[\n  {\n    \"call_type\": \"web_call\",\n    \"call_id\": \"Jabr9TXYYJHfvl6Syypi88rdAHYHmcq6\",\n    \"agent_id\": \"oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD\",\n    \"call_status\": \"registered\"\n  }\n]",
      "response_example_citation": "https://docs.retellai.com/api-references/list-calls",
      "sdk_installation_instructions": "import Retell from 'retell-sdk';",
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/list-calls"
    },
    {
      "endpoint_path": "POST /register-call",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/register-call",
      "request_example": null,
      "request_example_citation": "https://docs.retellai.com/api-references/register-call",
      "response_example": null,
      "response_example_citation": "https://docs.retellai.com/api-references/register-call",
      "sdk_installation_instructions": null,
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/register-call"
    },
    {
      "endpoint_path": "POST /create-agent",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/create-agent",
      "request_example": "const agentResponse = await client.agent.create({\n  response_engine: { llm_id: 'llm_234sdertfsdsfsdf', type: 'retell-llm' },\n  voice_id: '11labs-Adrian',\n});",
      "request_example_citation": "https://docs.retellai.com/api-references/create-agent",
      "response_example": "{\n  \"agent_id\": \"oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD\",\n  \"version\": 0,\n  \"response_engine\": {\n    \"type\": \"retell-llm\",\n    \"llm_id\": \"llm_234sdertfsdsfsdf\"\n  },\n  \"voice_id\": \"11labs-Adrian\",\n  \"agent_name\": \"Jarvis\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/create-agent",
      "sdk_installation_instructions": null,
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/create-agent"
    },
    {
      "endpoint_path": "GET /get-agent/{agent_id}",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/get-agent",
      "request_example": "Authorization: \"Bearer YOUR_API_KEY\"\nPath Parameter: agent_id: \"16b980523634a6dc504898cda492e939\"\nQuery Parameter: version: 1",
      "request_example_citation": "https://docs.retellai.com/api-references/get-agent",
      "response_example": "{\n  \"agent_id\": \"oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD\",\n  \"version\": 0,\n  \"voice_id\": \"11labs-Adrian\",\n  \"agent_name\": \"Jarvis\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/get-agent",
      "sdk_installation_instructions": null,
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/get-agent"
    },
    {
      "endpoint_path": "GET /list-agents",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/list-agents",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nconst agentResponses = await client.agent.list();\n\nconsole.log(agentResponses);",
      "request_example_citation": "https://docs.retellai.com/api-references/list-agents",
      "response_example": "[\n  {\n    \"agent_id\": \"oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD\",\n    \"version\": 0,\n    \"agent_name\": \"Jarvis\"\n  }\n]",
      "response_example_citation": "https://docs.retellai.com/api-references/list-agents",
      "sdk_installation_instructions": "import Retell from 'retell-sdk';",
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/list-agents"
    },
    {
      "endpoint_path": "PATCH /update-agent/{agent_id}",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/update-agent",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nconst agentResponse = await client.agent.update('16b980523634a6dc504898cda492e939', { agent_name: 'Jarvis' });\n\nconsole.log(agentResponse.agent_id);",
      "request_example_citation": "https://docs.retellai.com/api-references/update-agent",
      "response_example": "{\n  \"agent_id\": \"oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD\",\n  \"version\": 0,\n  \"agent_name\": \"Jarvis\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/update-agent",
      "sdk_installation_instructions": null,
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/update-agent"
    },
    {
      "endpoint_path": "DELETE /delete-agent/{agent_id}",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/delete-agent",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nawait client.agent.delete('oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD');",
      "request_example_citation": "https://docs.retellai.com/api-references/delete-agent",
      "response_example": "{\n  \"status\": \"error\",\n  \"message\": \"Invalid request format, please check API reference.\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/delete-agent",
      "sdk_installation_instructions": null,
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/delete-agent"
    },
    {
      "endpoint_path": "POST /create-phone-number",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/create-phone-number",
      "request_example": "{\n  \"area_code\": 415,\n  \"nickname\": \"Frontdesk Number\",\n  \"number_provider\": \"twilio\"\n}",
      "request_example_citation": "https://docs.retellai.com/api-references/create-phone-number",
      "response_example": "{\n  \"phone_number\": \"+14157774444\",\n  \"phone_number_pretty\": \"+1 (415) 777-4444\",\n  \"area_code\": 415,\n  \"nickname\": \"Frontdesk Number\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/create-phone-number",
      "sdk_installation_instructions": "import Retell from 'retell-sdk';",
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/create-phone-number"
    },
    {
      "endpoint_path": "GET /get-phone-number/{phone_number}",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/get-phone-number",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nconst phoneNumberResponse = await client.phoneNumber.retrieve('+14157774444');",
      "request_example_citation": "https://docs.retellai.com/api-references/get-phone-number",
      "response_example": "{\n  \"phone_number\": \"+14157774444\",\n  \"phone_number_pretty\": \"+1 (415) 777-4444\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/get-phone-number",
      "sdk_installation_instructions": "import Retell from 'retell-sdk';",
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/get-phone-number"
    },
    {
      "endpoint_path": "GET /list-phone-numbers",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/list-phone-numbers",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nconst phoneNumberResponses = await client.phoneNumber.list();\n\nconsole.log(phoneNumberResponses);",
      "request_example_citation": "https://docs.retellai.com/api-references/list-phone-numbers",
      "response_example": "[\n  {\n    \"phone_number\": \"+14157774444\",\n    \"phone_number_pretty\": \"+1 (415) 777-4444\"\n  }\n]",
      "response_example_citation": "https://docs.retellai.com/api-references/list-phone-numbers",
      "sdk_installation_instructions": null,
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/list-phone-numbers"
    },
    {
      "endpoint_path": "PATCH /update-phone-number/{phone_number}",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/update-phone-number",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nconst phoneNumberResponse = await client.phoneNumber.update('+14157774444', {\n  inbound_agent_id: 'oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD',\n  nickname: 'Frontdesk Number'\n});",
      "request_example_citation": "https://docs.retellai.com/api-references/update-phone-number",
      "response_example": "{\n  \"phone_number\": \"+14157774444\",\n  \"phone_number_pretty\": \"+1 (415) 777-4444\",\n  \"nickname\": \"Frontdesk Number\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/update-phone-number",
      "sdk_installation_instructions": null,
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/update-phone-number"
    },
    {
      "endpoint_path": "DELETE /delete-phone-number/{phone_number}",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/delete-phone-number",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nawait client.phoneNumber.delete('+14157774444');",
      "request_example_citation": "https://docs.retellai.com/api-references/delete-phone-number",
      "response_example": "{\n  \"status\": \"error\",\n  \"message\": \"API key is missing or invalid.\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/delete-phone-number",
      "sdk_installation_instructions": null,
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/delete-phone-number"
    },
    {
      "endpoint_path": "POST /create-retell-llm",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/create-retell-llm",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nconst llmResponse = await client.llm.create();\n\nconsole.log(llmResponse.llm_id);",
      "request_example_citation": "https://docs.retellai.com/api-references/create-retell-llm",
      "response_example": "{\n  \"llm_id\": \"oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD\",\n  \"version\": 1,\n  \"model\": \"gpt-4.1\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/create-retell-llm",
      "sdk_installation_instructions": "import Retell from 'retell-sdk';",
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/create-retell-llm"
    },
    {
      "endpoint_path": "GET /get-retell-llm/{llm_id}",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/get-retell-llm",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nconst llmResponse = await client.llm.retrieve('16b980523634a6dc504898cda492e939');\n\nconsole.log(llmResponse.llm_id);",
      "request_example_citation": "https://docs.retellai.com/api-references/get-retell-llm",
      "response_example": "{\n  \"llm_id\": \"oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD\",\n  \"version\": 1,\n  \"model\": \"gpt-4.1\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/get-retell-llm",
      "sdk_installation_instructions": null,
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/get-retell-llm"
    },
    {
      "endpoint_path": "PATCH /update-retell-llm/{llm_id}",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/update-retell-llm",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nconst llmResponse = await client.llm.update('16b980523634a6dc504898cda492e939', {\n  begin_message: 'Hey I am a virtual assistant calling from Retell Hospital.',\n});\n\nconsole.log(llmResponse.llm_id);",
      "request_example_citation": "https://docs.retellai.com/api-references/update-retell-llm",
      "response_example": "{\n  \"llm_id\": \"oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD\",\n  \"version\": 1,\n  \"model\": \"gpt-4.1\",\n  \"begin_message\": \"Hey I am a virtual assistant calling from Retell Hospital.\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/update-retell-llm",
      "sdk_installation_instructions": "import Retell from 'retell-sdk';",
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/update-retell-llm"
    },
    {
      "endpoint_path": "DELETE /delete-retell-llm/{llm_id}",
      "endpoint_path_citation": "https://docs.retellai.com/api-references/delete-retell-llm",
      "request_example": "import Retell from 'retell-sdk';\n\nconst client = new Retell({\n  apiKey: 'YOUR_RETELL_API_KEY',\n});\n\nawait client.llm.delete('oBeDLoLOeuAbiuaMFXRtDOLriTJ5tSxD');",
      "request_example_citation": "https://docs.retellai.com/api-references/delete-retell-llm",
      "response_example": "{\n  \"status\": \"error\",\n  \"message\": \"Invalid request format, please check API reference.\"\n}",
      "response_example_citation": "https://docs.retellai.com/api-references/delete-retell-llm",
      "sdk_installation_instructions": null,
      "sdk_installation_instructions_citation": "https://docs.retellai.com/api-references/delete-retell-llm"
    }
  ]
}