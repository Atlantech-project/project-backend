<!-- Generated from docs/reference/uml/ATLANTECH_Use_Case_Diagrams.md lines 260-554 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## Diagram 2: Port Setup, Operations and Recommendation

```mermaid
---
config:
  layout: elk
  theme: base
  fontFamily: Inter, Arial, sans-serif
  elk:
    nodePlacementStrategy: BRANDES_KOEPF
    mergeEdges: false
    considerModelOrder: NODES_AND_EDGES
    forceNodeModelOrder: true
  flowchart:
    padding: 18
  themeVariables:
    fontFamily: '"Inter", "Arial", sans-serif'
    fontSize: 16px
    primaryColor: '#FFFFFF'
    primaryBorderColor: '#407E8C'
    primaryTextColor: '#083A4F'
    lineColor: '#083A4F'
    clusterBkg: '#F7F5F2'
    clusterBorder: '#A58D66'
    edgeLabelBackground: '#FFFFFF'
    titleColor: '#083A4F'
---
flowchart LR

  subgraph G_P_AUTH[" "]
    direction LR
    OU_P_AUTH@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Office User", pos: "b", w: 40, h: 64, constraint: "on" }
    MU_P_AUTH@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Mobile User", pos: "b", w: 40, h: 64, constraint: "on" }
    PAD_P_AUTH@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Port Admin", pos: "b", w: 40, h: 64, constraint: "on" }
    subgraph P_AUTH["Access and Users"]
      direction TB
      U053(["Log in to Web Dashboard"])
      U054(["Log in to Mobile App"])
      U055(["Log Out"])
      U056(["Authorize Request by Role"])
      U060(["Re-authenticate and Resume Outbox"])
      U061(["Enforce Minimum App Version"])
      U057(["Manage Port Users"])
      U058(["Assign Roles and Terminal Scope"])
      U059(["Manage Devices and Revoke Tokens"])
    end
  end
  subgraph G_P_MD[" "]
    direction LR
    PAD_P_MD@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Port Admin", pos: "b", w: 40, h: 64, constraint: "on" }
    TGM_P_MD@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Tug Dept. Manager", pos: "b", w: 40, h: 64, constraint: "on" }
    PGM_P_MD@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Pilotage Dept. Manager", pos: "b", w: 40, h: 64, constraint: "on" }
    CTA_P_MD@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "CT Administrator", pos: "b", w: 40, h: 64, constraint: "on" }
    NCA_P_MD@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "NCT Administrator", pos: "b", w: 40, h: 64, constraint: "on" }
    MSO_P_MD@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Marine Safety Officer", pos: "b", w: 40, h: 64, constraint: "on" }
    subgraph P_MD["Port Master Data"]
      direction TB
      U062(["Manage Port Profile and Channel"])
      U063(["Manage Terminals"])
      U064(["Manage Berths"])
      U065(["Manage Tugboat Fleet"])
      U066(["Manage Tug Masters"])
      U067(["Manage Pilot Roster"])
      U068(["Manage CT Equipment and Cranes"])
      U069(["Manage Container Yard"])
      U070(["Manage NCT Storage"])
      U071(["Define Safety Constraints and Environmental Limits"])
      U072(["View Master-Data History"])
      U073(["View Port Audit Log"])
    end
  end
  subgraph G_P_EDI[" "]
    direction LR
    PAS_P_EDI@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Port Authority Staff", pos: "b", w: 40, h: 64, constraint: "on" }
    EDI_P_EDI@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyM0MDdFOEMnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "EDI Parser", pos: "b", w: 40, h: 64, constraint: "on" }
    subgraph P_EDI["BAPLIE Cargo Manifest"]
      direction TB
      U074(["Upload BAPLIE File"])
      U075(["Parse and Validate BAPLIE"])
      U076(["Review BAPLIE Diagnostics"])
      U077(["Correct Staged BAPLIE Data"])
      U145(["Withdraw BAPLIE Upload"])
    end
  end
  subgraph G_P_REQ[" "]
    direction LR
    PAS_P_REQ@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Port Authority Staff", pos: "b", w: 40, h: 64, constraint: "on" }
    AI1_P_REQ@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyM0MDdFOEMnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "AI Part 1 Engine", pos: "b", w: 40, h: 64, constraint: "on" }
    subgraph P_REQ["Berthing Request and Entry Window"]
      direction TB
      U078(["Create Berthing Request"])
      U080(["Edit or Cancel Request"])
      U081(["Record Preliminary Approval"])
      U082(["Return or Reject Request"])
      U083(["Record Customs Approval"])
      U084(["Compute Entry-Time Window"])
      U085(["Confirm, Reject All or Reopen Entry Window"])
      U149(["Submit Manual Environmental Data"])
    end
  end
  subgraph G_P_WX[" "]
    direction LR
    OU_P_WX@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Office User", pos: "b", w: 40, h: 64, constraint: "on" }
    AI1_P_WX@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyM0MDdFOEMnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "AI Part 1 Engine", pos: "b", w: 40, h: 64, constraint: "on" }
    WX_P_WX@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyM0MDdFOEMnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Weather and Sea-Level Providers", pos: "b", w: 40, h: 64, constraint: "on" }
    SCH_P_WX@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyM0MDdFOEMnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Scheduler", pos: "b", w: 40, h: 64, constraint: "on" }
    subgraph P_WX["Weather and Tide"]
      direction TB
      U108(["Poll Weather Data Every 6 Hours"])
      U111(["Handle Weather Source Failure"])
      U109(["Fetch Weather and Sea Level on Demand"])
      U110(["Compute Astronomical Tide"])
      U112(["View Environmental Conditions"])
    end
  end
  subgraph G_P_REC[" "]
    direction LR
    OU_P_REC@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Office User", pos: "b", w: 40, h: 64, constraint: "on" }
    PAD_P_REC@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Port Admin", pos: "b", w: 40, h: 64, constraint: "on" }
    PAS_P_REC@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Port Authority Staff", pos: "b", w: 40, h: 64, constraint: "on" }
    AI2_P_REC@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyM0MDdFOEMnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "AI Part 2 Engine", pos: "b", w: 40, h: 64, constraint: "on" }
    subgraph P_REC["Recommendation and Operational Plan"]
      direction TB
      U086(["Generate Port-wide Recommendations"])
      U087(["Review Recommendation and Rationale"])
      U088(["Override Component"])
      U089(["Re-generate Recommendation or Delay Re-plan"])
      U090(["Confirm Operational Plan"])
      U091(["Cancel Plan and Release Resources"])
      U092(["View Unified Port State"])
    end
  end
  subgraph G_P_DIS[" "]
    direction LR
    AI2_P_DIS@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyM0MDdFOEMnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "AI Part 2 Engine", pos: "b", w: 40, h: 64, constraint: "on" }
    SCH_P_DIS@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyM0MDdFOEMnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Scheduler", pos: "b", w: 40, h: 64, constraint: "on" }
    PDO_P_DIS@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Pilotage Dept. Officer", pos: "b", w: 40, h: 64, constraint: "on" }
    TDO_P_DIS@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Tug Dept. Officer", pos: "b", w: 40, h: 64, constraint: "on" }
    CTP_P_DIS@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "CT Operation Planner", pos: "b", w: 40, h: 64, constraint: "on" }
    MPT_P_DIS@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Marine Pilot / Tug Master", pos: "b", w: 40, h: 64, constraint: "on" }
    subgraph P_DIS["Dispatch and Container Yard"]
      direction TB
      U093(["Assign Pilotage Component"])
      U094(["Assign Tug Component"])
      U095(["Send Task Notifications and Escalations"])
      U096(["Acknowledge Notification"])
      U097(["Generate Yard Placement Recommendation"])
      U098(["Accept, Adjust or Reject Yard Recommendation"])
      U099(["Present TOS-Ready Plan and Record Export"])
    end
  end
  subgraph G_P_DEV[" "]
    direction LR
    PIT_P_DEV@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Port IT Administrator", pos: "b", w: 40, h: 64, constraint: "on" }
    MDM_P_DEV@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyM0MDdFOEMnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "MDM Platform", pos: "b", w: 40, h: 64, constraint: "on" }
    VPN_P_DEV@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyM0MDdFOEMnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "VPN Gateway", pos: "b", w: 40, h: 64, constraint: "on" }
    DEV_P_DEV@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyM0MDdFOEMnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Managed Mobile Device", pos: "b", w: 40, h: 64, constraint: "on" }
    RFU_P_DEV@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Remote Field User", pos: "b", w: 40, h: 64, constraint: "on" }
    subgraph P_DEV["Devices and VPN"]
      direction TB
      U128(["Enroll Device in MDM"])
      U129(["Issue Device Certificate"])
      U130(["Distribute App and VPN Profile"])
      U131(["Revoke Certificate and Selective Wipe"])
      U132(["Establish Always-on VPN"])
      U133(["Validate Device Certificate"])
      U134(["Manage VPN Gateway"])
    end
  end
  subgraph G_P_NET[" "]
    direction LR
    PIT_P_NET@{ img: "data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc0MCcgaGVpZ2h0PSc2NCcgdmlld0JveD0nMCAwIDQwIDY0Jz48ZyBmaWxsPSdub25lJyBzdHJva2U9JyMwODNBNEYnIHN0cm9rZS13aWR0aD0nMi42JyBzdHJva2UtbGluZWNhcD0ncm91bmQnIHN0cm9rZS1saW5lam9pbj0ncm91bmQnPjxjaXJjbGUgY3g9JzIwJyBjeT0nOScgcj0nNycvPjxwYXRoIGQ9J00yMCAxNnYyNE01IDI2aDMwTTIwIDQwTDggNjBNMjAgNDBsMTIgMjAnLz48L2c+PC9zdmc+", label: "Port IT Administrator", pos: "b", w: 40, h: 64, constraint: "on" }
    subgraph P_NET["Port Network and Edge"]
      direction TB
      U135(["Manage Firewall Rules"])
      U136(["Manage Internal Split DNS"])
      U137(["Trust Root CA on Office PCs"])
      U140(["Troubleshoot Connectivity"])
    end
  end
  NOTE1["Every write action in this diagram includes Enforce Read-Only Mode (Diagram 1). Adding a user, terminal or berth also includes Enforce Plan Limits (Diagram 1)."]

  OU_P_AUTH --- U053
  OU_P_AUTH --- U055
  OU_P_REC --- U092
  OU_P_WX --- U112
  MU_P_AUTH --- U054
  MU_P_AUTH --- U055
  MU_P_AUTH --- U060
  PAD_P_AUTH --- U057
  PAD_P_AUTH --- U058
  PAD_P_AUTH --- U059
  PAD_P_MD --- U062
  PAD_P_MD --- U063
  PAD_P_MD --- U064
  PAD_P_MD --- U072
  PAD_P_MD --- U073
  PAD_P_REC --- U091
  TGM_P_MD --- U065
  TGM_P_MD --- U066
  PGM_P_MD --- U067
  CTA_P_MD --- U068
  CTA_P_MD --- U069
  NCA_P_MD --- U070
  MSO_P_MD --- U071
  PAS_P_EDI --- U074
  PAS_P_EDI --- U076
  PAS_P_EDI --- U077
  PAS_P_EDI --- U145
  PAS_P_REQ --- U078
  PAS_P_REQ --- U080
  PAS_P_REQ --- U081
  PAS_P_REQ --- U082
  PAS_P_REQ --- U083
  PAS_P_REQ --- U085
  PAS_P_REQ --- U149
  PAS_P_REC --- U087
  PAS_P_REC --- U088
  PAS_P_REC --- U090
  PAS_P_REC --- U091
  U075 --- EDI_P_EDI
  U084 --- AI1_P_REQ
  U109 --- AI1_P_WX
  U110 --- AI1_P_WX
  U086 --- AI2_P_REC
  U089 --- AI2_P_REC
  U097 --- AI2_P_DIS
  U108 --- WX_P_WX
  U109 --- WX_P_WX
  U095 --- SCH_P_DIS
  U108 --- SCH_P_WX
  PDO_P_DIS --- U093
  TDO_P_DIS --- U094
  CTP_P_DIS --- U098
  CTP_P_DIS --- U099
  MPT_P_DIS --- U096
  PIT_P_DEV --- U128
  PIT_P_DEV --- U131
  PIT_P_DEV --- U134
  PIT_P_NET --- U135
  PIT_P_NET --- U136
  PIT_P_NET --- U137
  PIT_P_NET --- U140
  U129 --- MDM_P_DEV
  U130 --- MDM_P_DEV
  U131 --- MDM_P_DEV
  U133 --- VPN_P_DEV
  U134 --- VPN_P_DEV
  U130 --- DEV_P_DEV
  RFU_P_DEV --- U132

  U053 -. "«include»" .-> U056
  U054 -. "«include»" .-> U056
  U054 -. "«include»" .-> U061
  U060 -. "«extend»" .-> U054
  U074 -. "«include»" .-> U075
  U077 -. "«include»" .-> U075
  U076 -. "«extend»" .-> U074
  U077 -. "«extend»" .-> U076
  U082 -. "«extend»" .-> U081
  U083 -. "«include»" .-> U084
  U084 -. "«include»" .-> U109
  U084 -. "«include»" .-> U110
  U149 -. "«extend»" .-> U084
  U111 -. "«extend»" .-> U108
  U088 -. "«extend»" .-> U087
  U089 -. "«extend»" .-> U086
  U090 -. "«include»" .-> U087
  U093 -. "«include»" .-> U095
  U094 -. "«include»" .-> U095
  U099 -. "«extend»" .-> U098
  U128 -. "«include»" .-> U129
  U128 -. "«include»" .-> U130
  U132 -. "«include»" .-> U133

  classDef uc fill:#FFFFFF,stroke:#407E8C,stroke-width:1.6px,color:#083A4F
  classDef ref fill:#E5E1DD,stroke:#A58D66,stroke-width:1.4px,stroke-dasharray:5 4,color:#083A4F
  classDef actor fill:none,stroke:none,color:#083A4F,font-weight:600
  classDef note fill:#FDFBF7,stroke:#A58D66,stroke-width:1.2px,color:#083A4F
  classDef sysactor fill:none,stroke:none,color:#407E8C,font-weight:600

  class U053,U054,U055,U056,U060,U061,U057,U058,U059,U062,U063,U064,U065,U066,U067,U068,U069,U070,U071,U072,U073,U074,U075,U076,U077,U145,U078,U080,U081,U082,U083,U084,U085,U149,U108,U111,U109,U110,U112,U086,U087,U088,U089,U090,U091,U092,U093,U094,U095,U096,U097,U098,U099,U128,U129,U130,U131,U132,U133,U134,U135,U136,U137,U140 uc
  class NOTE1 note
  class OU_P_AUTH,OU_P_REC,OU_P_WX,MU_P_AUTH,PAD_P_AUTH,PAD_P_MD,PAD_P_REC,TGM_P_MD,PGM_P_MD,CTA_P_MD,NCA_P_MD,MSO_P_MD,PAS_P_EDI,PAS_P_REQ,PAS_P_REC,PDO_P_DIS,TDO_P_DIS,CTP_P_DIS,MPT_P_DIS,PIT_P_DEV,PIT_P_NET,RFU_P_DEV actor
  class EDI_P_EDI,AI1_P_REQ,AI1_P_WX,AI2_P_REC,AI2_P_DIS,WX_P_WX,SCH_P_DIS,SCH_P_WX,MDM_P_DEV,VPN_P_DEV,DEV_P_DEV sysactor
  style G_P_AUTH fill:none,stroke:none
  style G_P_MD fill:none,stroke:none
  style G_P_EDI fill:none,stroke:none
  style G_P_REQ fill:none,stroke:none
  style G_P_WX fill:none,stroke:none
  style G_P_REC fill:none,stroke:none
  style G_P_DIS fill:none,stroke:none
  style G_P_DEV fill:none,stroke:none
  style G_P_NET fill:none,stroke:none
```
