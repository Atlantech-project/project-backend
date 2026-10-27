<!-- Generated from docs/reference/uml/UML_Context.md lines 98-103 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## 6. Notation

- **Use case diagrams**: stick-figure actors (human in navy, system actors in teal), use cases as ovals grouped in packages, plain lines for associations, dashed arrows labelled «include» or «extend».
- **Sequence diagrams**: actor = person; boundary = user interface; control = API, service, worker or scheduler; database = data store; queue = Redis queue; plain rectangle = external system. Solid arrow = call, dashed arrow = reply. `alt`, `opt`, `loop`, `par` frames. No numbering; order is top to bottom.
- **Activity diagrams**: swimlanes per participant, rounded rectangles for actions, diamonds for decisions (answers on the outgoing arrows), bars for fork and join, filled circle with ring for final nodes (a diagram may have several), beige boxes for notes. A diamond `READ_ONLY?` asks whether the port is in read-only mode.
- **State machines**: `event [guard] / action` on transitions; `[FULL]` means the transition is refused in read-only mode; a state box with a lower compartment lists internal transitions; dashed regions inside a composite state run in parallel.
