import { router } from 'expo-router';

import { createProject } from '@/api/projects';
import { ProjectForm } from '@/components/ProjectForm';

export default function NewProject() {
  return (
    <ProjectForm
      submitLabel="Post project"
      onSubmit={async (payload) => {
        const project = await createProject(payload);
        router.replace(`/project/${project.id}`);
      }}
    />
  );
}
