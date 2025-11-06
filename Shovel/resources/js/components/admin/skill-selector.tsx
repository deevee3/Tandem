import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

interface Skill {
    id: number;
    name: string;
    description?: string | null;
}

interface SkillSelectorProps {
    skills: Skill[];
    selectedIds: number[];
    onChange: (nextIds: number[]) => void;
    className?: string;
    emptyMessage?: string;
}

export default function SkillSelector({ skills, selectedIds, onChange, className, emptyMessage = 'No skills available' }: SkillSelectorProps) {
    const toggleSkill = (skillId: number, checked: boolean) => {
        if (checked) {
            onChange([...selectedIds, skillId]);

            return;
        }

        onChange(selectedIds.filter((id) => id !== skillId));
    };

    return (
        <div className={cn('border rounded-md p-4 max-h-60 overflow-y-auto', className)}>
            {skills.length === 0 ? (
                <p className="text-sm text-muted-foreground text-center py-2">{emptyMessage}</p>
            ) : (
                <div className="grid gap-3">
                    {skills.map((skill) => (
                        <div key={skill.id} className="flex items-center space-x-2">
                            <Checkbox
                                id={`skill-${skill.id}`}
                                checked={selectedIds.includes(skill.id)}
                                onCheckedChange={(checked) => toggleSkill(skill.id, Boolean(checked))}
                            />
                            <label
                                htmlFor={`skill-${skill.id}`}
                                className="text-sm font-medium leading-none cursor-pointer text-foreground"
                            >
                                {skill.name}
                                {skill.description ? (
                                    <span className="text-muted-foreground ml-2">({skill.description})</span>
                                ) : null}
                            </label>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
